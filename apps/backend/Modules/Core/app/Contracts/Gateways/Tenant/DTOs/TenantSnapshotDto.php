<?php

namespace Modules\Core\Contracts\Gateways\Tenant\DTOs;

/**
 * Tenant identity as it crosses the Core ⇄ Tenant boundary. Immutable and
 * free of secrets.
 */
final class TenantSnapshotDto
{
    /** @param  list<string>  $domains */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly bool $suspended,
        public readonly array $domains = [],
    ) {}
}
