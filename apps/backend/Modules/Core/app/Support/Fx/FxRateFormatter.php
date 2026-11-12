<?php

namespace Modules\Core\Support\Fx;

/**
 * Exact-decimal rate formatter (review F2/F11/F20). Pure string
 * arithmetic — no PHP float and (since ext-bcmath is not a platform
 * dependency) no BCMath: rounding is a digit-inspection + string-carry
 * algorithm.
 *
 * Contract: accept any positive decimal string within the input-safety
 * bound (32 chars — applied BEFORE any arithmetic), round half-up to
 * exactly 8 fraction digits, then validate the ROUNDED result against
 * the decimal(16,8) column (≤ 8 integer digits, > 0). Nine-plus
 * fraction digits are accepted input; a value that ROUNDS to zero
 * (e.g. 0.000000001) is rejected after rounding.
 *
 * Returns null for any invalid input — the sync engine treats null as
 * "skip this currency" (per-rate failure, review F16).
 */
class FxRateFormatter
{
    /** Input-safety limit on the raw numeric token (before arithmetic). */
    public const int MAX_TOKEN_LENGTH = 32;

    public const int SCALE = 8;

    /** decimal(16,8) ⇒ at most 8 integer digits. */
    public const int MAX_INTEGER_DIGITS = 8;

    public static function format(string $rate): ?string
    {
        // Input-safety bound first — no normalization work on hostile
        // tokens (F20).
        if ($rate === '' || strlen($rate) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        // Digits with an optional single dot — no sign, no exponent,
        // no thousands separators.
        if (! preg_match('/^\d+(\.\d+)?$/', $rate)) {
            return null;
        }

        [$integer, $fraction] = array_pad(explode('.', $rate, 2), 2, '');

        // Half-up at 8 fraction digits: inspect digit 9.
        $kept = self::substr_pad_fraction($fraction, self::SCALE);
        $roundDigit = $fraction[self::SCALE] ?? '0';

        if ($roundDigit >= '5') {
            [$kept, $carry] = self::incrementFraction($kept);

            if ($carry) {
                $integer = self::incrementInteger($integer);
            }
        }

        // Normalize the integer part (strip leading zeros).
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;

        // Validate the ROUNDED result against decimal(16,8) storage.
        if (strlen($integer) > self::MAX_INTEGER_DIGITS) {
            return null; // carry overflow, e.g. 99999999.999999995
        }

        $formatted = $integer.'.'.$kept;

        if ($formatted === '0.'.str_repeat('0', self::SCALE)) {
            return null; // rounds to zero — not a usable rate (F11)
        }

        return $formatted;
    }

    /**
     * True when the token is structurally valid but carries exponent
     * notation — such rates parse out of the raw JSON but fail
     * financial validation and are skipped per rate (F16).
     */
    public static function isExponentNotation(string $token): bool
    {
        return preg_match('/^[+-]?\d+(\.\d+)?[eE][+-]?\d+$/', $token) === 1;
    }

    /**
     * Pad/trim the fraction string to exactly $scale digits.
     */
    private static function substr_pad_fraction(string $fraction, int $scale): string
    {
        return substr($fraction.str_repeat('0', $scale), 0, $scale);
    }

    /**
     * Add one to an $scale-digit fraction string. Returns
     * [newFraction, carriedIntoInteger].
     *
     * @return array{0: string, 1: bool}
     */
    private static function incrementFraction(string $fraction): array
    {
        $digits = str_split($fraction);

        for ($i = count($digits) - 1; $i >= 0; $i--) {
            if ($digits[$i] === '9') {
                $digits[$i] = '0';

                continue;
            }

            $digits[$i] = (string) ((int) $digits[$i] + 1);

            return [implode('', $digits), false];
        }

        // All nines — the fraction rolled over entirely.
        return [str_repeat('0', strlen($fraction)), true];
    }

    /**
     * Add one to an arbitrary-length non-negative integer string.
     */
    private static function incrementInteger(string $integer): string
    {
        $digits = str_split($integer === '' ? '0' : $integer);

        for ($i = count($digits) - 1; $i >= 0; $i--) {
            if ($digits[$i] === '9') {
                $digits[$i] = '0';

                continue;
            }

            $digits[$i] = (string) ((int) $digits[$i] + 1);

            return implode('', $digits);
        }

        return '1'.implode('', $digits);
    }
}
