<?php

namespace Modules\Core\Contracts\Gateways\Tenant;

/**
 * A store's enabled-language and enabled-currency selections (§6.7.1).
 * The owning module implements it from its own tenant-scoped models so
 * runtime consumers never reach across module boundaries.
 */
interface StoreSelectionGatewayInterface
{
    /** @return list<int> */
    public function enabledLanguageIds(int $tenantId): array;

    /** @return list<int> */
    public function enabledCurrencyIds(int $tenantId): array;
}
