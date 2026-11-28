<?php

namespace Modules\Core\Contracts\Gateways\Tenant;

/**
 * The process-global part of a store's runtime (§6.7 items 1, 3, 4).
 * Implemented by the Setting module; Core's queue lifecycle captures it
 * before an attempt and restores it afterwards, including for jobs whose
 * payload grants no tenant.
 */
interface StoreRuntimeInterface
{
    /** @return array<string, mixed> an opaque snapshot of the current runtime */
    public function capture(): array;

    /** Apply the given store's runtime inside its tenant context. */
    public function apply(int $tenantId): void;

    /**
     * @param  array<string, mixed>  $state  a snapshot from capture()
     */
    public function restore(array $state): void;
}
