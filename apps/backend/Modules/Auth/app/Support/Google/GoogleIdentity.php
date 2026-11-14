<?php

namespace Modules\Auth\Support\Google;

/**
 * The verified identity carried by a Google ID token. Only `sub` is the
 * permanent identity; `email` and `email_verified` feed the account
 * policy, and `name`/`picture` are passed through but intentionally not
 * persisted anywhere (no Google profile data is stored in v1).
 */
final class GoogleIdentity
{
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly bool $emailVerified,
        /** Normalized hosted domain (lowercased) or null when absent. */
        public readonly ?string $hd,
        public readonly GoogleEmailClass $class,
        public readonly ?string $name,
        public readonly ?string $picture,
    ) {}
}
