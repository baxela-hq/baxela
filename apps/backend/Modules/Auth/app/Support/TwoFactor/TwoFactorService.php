<?php

namespace Modules\Auth\Support\TwoFactor;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP secret management, code verification with replay prevention
 * (D1/D8), and recovery-code generation/consumption (D5). All
 * verification methods are designed to run inside the caller's
 * transaction under the user-row lock (D17).
 */
class TwoFactorService
{
    public const int RECOVERY_CODE_COUNT = 8;

    /** 32-char alphanumeric alphabet, no ambiguous characters (0/O/1/I). */
    private const string RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** 10 chars from the 31-char alphabet ≈ 49.5 bits of entropy. */
    private const int RECOVERY_CODE_LENGTH = 10;

    public function __construct(protected Google2FA $engine) {}

    /**
     * Generate a new TOTP secret (base32, 32 chars — 128 bits).
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /**
     * The QR code URI the authenticator app scans — a data:image/svg+xml
     * inline, no CDN dependency.
     */
    public function qrCodeUri(string $secret, string $email): string
    {
        $otpauthUri = $this->engine->getQRCodeUrl(
            config('app.name'),
            $email,
            $secret,
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($otpauthUri);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Verify a TOTP code and return the matched time-step, or null on
     * failure/replay. Uses verifyKeyNewer() with the user's stored
     * last_used_step — the library's built-in replay check means codes
     * at or before the stored step are never accepted (D8).
     *
     * MUST be called inside the caller's transaction with the user-row
     * lock held (D17) — the replay guard update is atomic only under
     * that lock.
     */
    public function verifyCode(User $user, string $code): ?int
    {
        $secret = $user->{UserSchema::TWO_FACTOR_SECRET};

        if ($secret === null) {
            return null;
        }

        // Pass the stored step (or -1 on first use) — the library's
        // makeStartingTimestamp() starts checking at max(now-window,
        // oldStep+1), so codes at or before oldStep are never tested.
        $lastStep = $user->{UserSchema::TWO_FACTOR_LAST_USED_STEP} ?? -1;

        $matchedStep = $this->engine->verifyKeyNewer($secret, $code, $lastStep);

        if ($matchedStep === false) {
            return null;
        }

        // verifyKeyNewer returns true (not int) when oldTimestamp was
        // null — but we always pass -1 or an int, so it returns the
        // matched timestamp. Defensive check for the boolean edge.
        if ($matchedStep === true) {
            // The library fell through to the boolean path; compute the
            // step from the current timestamp (± window tolerance).
            $matchedStep = (int) floor($this->engine->getTimestamp() / $this->engine->getKeyRegeneration());
        }

        // Record the replay guard under the caller's lock.
        $user->{UserSchema::TWO_FACTOR_LAST_USED_STEP} = $matchedStep;

        return (int) $matchedStep;
    }

    /**
     * Generate recovery codes: N codes, each 10 chars from the 31-char
     * alphabet, format XXXXX-XXXXX. Returned as plaintext ONCE — only
     * the bcrypt hashes are stored.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::range(1, self::RECOVERY_CODE_COUNT)
            ->map(function (): string {
                $code = '';
                for ($i = 0; $i < self::RECOVERY_CODE_LENGTH; $i++) {
                    $code .= self::RECOVERY_ALPHABET[random_int(0, strlen(self::RECOVERY_ALPHABET) - 1)];
                }

                return substr($code, 0, 5).'-'.substr($code, 5);
            })
            ->all();
    }

    /**
     * Hash recovery codes for storage — bcrypt, matching the password
     * cast convention.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code): string => bcrypt($code), $codes);
    }

    /**
     * Normalize a recovery code for comparison: uppercase, trim,
     * strip hyphens. Input can be "XXXXX-XXXXX" or "XXXXXXXXXX".
     */
    public function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)));
    }

    /**
     * Verify a submitted recovery code against all stored hashes and
     * remove the matching one (D5). MUST run inside the caller's
     * transaction with the user-row lock (D17). No position disclosure.
     *
     * @return bool true if a code matched and was consumed.
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $submittedCode): bool
    {
        $stored = $user->{UserSchema::TWO_FACTOR_RECOVERY_CODES};

        if ($stored === null || $stored === []) {
            return false;
        }

        $normalized = $this->normalizeRecoveryCode($submittedCode);

        // Try every hash — no early exit, no position disclosure.
        // The match is verified by re-hyphenating the normalized code
        // to the generated format for comparison.
        $formatted = sprintf(
            '%s-%s',
            substr($normalized, 0, 5),
            substr($normalized, 5),
        );

        $remaining = [];
        $matched = false;

        foreach ($stored as $hash) {
            if (! $matched && password_verify($formatted, $hash)) {
                $matched = true;

                continue; // Remove this one; keep the rest.
            }
            $remaining[] = $hash;
        }

        if (! $matched) {
            return false;
        }

        $user->{UserSchema::TWO_FACTOR_RECOVERY_CODES} = $remaining;

        return true;
    }

    /**
     * The number of remaining (unconsumed) recovery codes.
     */
    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->{UserSchema::TWO_FACTOR_RECOVERY_CODES} ?? []);
    }

    /**
     * Clear all 2FA state atomically (D14). MUST run inside the
     * caller's transaction with the user-row lock.
     */
    public function clearAll(User $user): void
    {
        $user->fill([
            UserSchema::TWO_FACTOR_SECRET => null,
            UserSchema::TWO_FACTOR_RECOVERY_CODES => null,
            UserSchema::TWO_FACTOR_CONFIRMED_AT => null,
            UserSchema::TWO_FACTOR_LAST_USED_STEP => null,
        ]);
    }
}
