<?php

namespace Modules\Tenant\Gateways;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\Gateways\Tenant\StoreSelectionGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\TenantCurrency;
use Modules\Tenant\Models\TenantLanguage;
use Modules\Tenant\Schemas\TenantCurrency\TenantCurrencySchema;
use Modules\Tenant\Schemas\TenantLanguage\TenantLanguageSchema;

/**
 * Store-level language/currency enablement (§6.7.1). The rows are
 * store-owned, so every read runs inside the tenant the caller asked
 * about; the id argument is never used to widen a query.
 */
class StoreSelectionGateway implements StoreSelectionGatewayInterface
{
    public function enabledLanguageIds(int $tenantId): array
    {
        return $this->idsFor(
            $tenantId,
            static fn (): Builder => TenantLanguage::query(),
            TenantLanguageSchema::LANGUAGE_ID,
        );
    }

    public function enabledCurrencyIds(int $tenantId): array
    {
        return $this->idsFor(
            $tenantId,
            static fn (): Builder => TenantCurrency::query(),
            TenantCurrencySchema::CURRENCY_ID,
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Closure(): Builder<TModel>  $queryFactory
     * @return list<int>
     */
    private function idsFor(int $tenantId, Closure $queryFactory, string $column): array
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        // Build the builder inside the requested tenant: Eloquent applies
        // TenantScope when the query is created, not when it is executed.
        return $context->runForTenant($tenantId, static fn (): array => $queryFactory()
            ->orderBy($column)
            ->pluck($column)
            ->map(static fn ($id): int => (int) $id)
            ->all());
    }
}
