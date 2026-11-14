<?php

namespace Modules\Core\Contracts\Gateways\Payment;

/**
 * Resolution of payment-driver credentials: database first (encrypted at
 * rest, admin-managed — marketplace P5.2), env/config fallback second,
 * fail-closed when a stored value cannot be decrypted. Implemented in
 * the Payment module; Core depends only on this abstraction.
 *
 * The state of a credential is always available without its value —
 * admin API responses carry states, never plaintext or ciphertext.
 */
interface PaymentCredentialResolverInterface
{
    /** A stored, decryptable credential — the effective value. */
    public const string KEY_STATE_STORED = 'stored';

    /** No stored row; the env-backed config value is the effective credential. */
    public const string KEY_STATE_ENV = 'env';

    /** No stored row and no usable env value. */
    public const string KEY_STATE_NONE = 'none';

    /** A stored row exists but cannot be decrypted (APP_KEY rotation signature) — resolves to null, env deliberately NOT consulted. */
    public const string KEY_STATE_UNDECRYPTABLE = 'undecryptable';

    /**
     * The effective credential value, or null when unconfigured. The
     * legacy manager truthiness semantics are preserved: a falsy env
     * value ('' or '0') is unconfigured, exactly like the old
     * `! config(...)` check.
     */
    public function credential(string $driver, string $key): ?string;

    /**
     * The resolution state for one credential key — same rules, same
     * precedence as credential(): they can never disagree.
     */
    public function stateFor(string $driver, string $key): string;

    /**
     * Forget the cached encrypted credential map. Callers invoke this
     * only after a committed mutation (marketplace P5.2 F4/F5).
     */
    public function invalidate(): void;

    /**
     * Re-snapshot the environment baseline — controlled situations only
     * (tests, deliberate env reloads), never ordinary requests: the
     * baseline is process-local and immutable during normal operation.
     */
    public function refreshEnvBaseline(): void;
}
