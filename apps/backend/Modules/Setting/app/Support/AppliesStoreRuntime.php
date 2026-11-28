<?php

namespace Modules\Setting\Support;

use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Setting\FxSettingsResolverInterface;
use Modules\Core\Contracts\Gateways\Setting\PlatformSettingsInterface;
use Modules\Core\Contracts\Gateways\Setting\PricingModeResolverInterface;
use Modules\Core\Contracts\Gateways\Tax\TaxGatewayInterface;
use Modules\Core\Contracts\Gateways\Tenant\StoreRuntimeInterface;
use Modules\Core\Contracts\Gateways\Tenant\StoreSelectionGatewayInterface;
use Modules\Core\Exceptions\PlatformSettingMissing;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Core\Support\SupportedLocales;
use Modules\Core\Support\Tenancy\TenancyExceptions;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

/**
 * The process-global part of a store runtime (§6.7 items 1, 3, 4). Every
 * application — the TenantResolved listener and the queue lifecycle —
 * calls apply(); the queue lifecycle captures before an attempt and
 * restores afterwards, so one worker can serve two stores in sequence.
 */
class AppliesStoreRuntime implements StoreRuntimeInterface
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AppliesStoreTimezone $timezone,
        private readonly AppliesStoreIdentity $identity,
        private readonly PricingModeResolverInterface $pricingMode,
        private readonly FxSettingsResolverInterface $fxSettings,
        private readonly PlatformSettingsInterface $platformSettings,
        private readonly CoreGatewayInterface $coreGateway,
        private readonly StoreSelectionGatewayInterface $storeSelections,
        private readonly TaxGatewayInterface $taxGateway,
    ) {}

    public function apply(int $tenantId): void
    {
        $this->context->runForTenant($tenantId, function () use ($tenantId): void {
            try {
                ($this->timezone)();
                $identity = ($this->identity)();

                $pricesIncludeTax = $this->pricingMode->pricesIncludeTax();
                $this->applyFx();

                $tenant = $this->context->tenant();
                $locale = $this->applyLanguage($tenantId);
                $this->applyCurrencies($tenantId);

                $taxClassId = null;
                try {
                    $taxClassId = $this->taxGateway->defaultTaxClassId();
                } catch (Throwable $e) {
                    if (TenancyExceptions::isTenancy($e)) {
                        throw $e;
                    }
                }

                config([
                    'store.prices_include_tax' => $pricesIncludeTax,
                    'store.default_tax_class_id' => $taxClassId,
                    'store.platform_identity' => $this->platformIdentity(),
                    'mail.from.name' => $identity['legal_name'] ?: ($tenant?->name ?: config('mail.from.name')),
                    // Locale is explicit so the framework's translator agrees
                    // with the store's default language on every request.
                    'app.locale' => $locale,
                ]);

                app()->setLocale($locale);
            } catch (PlatformSettingMissing $e) {
                // Configuration defects fail closed (rows 81/93).
                throw $e;
            } catch (Throwable $e) {
                if (TenancyExceptions::isTenancy($e)) {
                    throw $e;
                }

                // Fresh installs and requests that do not need the database
                // (an unauthenticated admin probe, for one) keep the
                // framework defaults rather than turning a 401 into a 500.
            }
        });
    }

    public function capture(): array
    {
        return [
            'php_timezone' => date_default_timezone_get(),
            'app_timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'mail_from_name' => config('mail.from.name'),
            'store' => config('store'),
            'supported_locales' => SupportedLocales::current(),
        ];
    }

    public function restore(array $state): void
    {
        $phpTimezone = $state['php_timezone'] ?? null;

        if (is_string($phpTimezone) && $phpTimezone !== '') {
            date_default_timezone_set($phpTimezone);
        }

        config([
            'app.timezone' => $state['app_timezone'] ?? null,
            'app.locale' => $state['locale'] ?? null,
            'mail.from.name' => $state['mail_from_name'] ?? null,
            'store' => $state['store'] ?? [],
        ]);

        $locale = $state['locale'];

        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }

        $codes = $state['supported_locales'] ?? null;

        if (is_array($codes)) {
            /** @var array<string> $codes */
            SupportedLocales::useCodes($codes);
        } else {
            // Only this tenant's entry existed before the job; another
            // store's cached list must not be disturbed.
            SupportedLocales::forget();
        }
    }

    /**
     * FX config/status is platform data via the allowlisted resolver. A
     * freshly installed database whose platform rows have not been seeded
     * yet has nothing to apply; a partially seeded set still fails loudly.
     */
    protected function applyFx(): void
    {
        try {
            if (! $this->hasPlatformFxRows()) {
                return;
            }

            $settings = $this->fxSettings->settings();
            $status = $this->fxSettings->lastSyncStatus();

            config([
                'store.fx' => $settings,
                'store.fx_sync_status' => $status,
            ]);
        } catch (PlatformSettingMissing $e) {
            throw $e;
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }
        }
    }

    protected function hasPlatformFxRows(): bool
    {
        foreach ([
            PlatformSettingsInterface::FX_AUTO_SYNC_ENABLED,
            PlatformSettingsInterface::FX_PROVIDER,
            PlatformSettingsInterface::FX_API_KEY,
            PlatformSettingsInterface::FX_SYNC_INTERVAL_HOURS,
        ] as $name) {
            if ($this->platformSettings->has($name)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{name: string, url: string}|null */
    protected function platformIdentity(): ?array
    {
        if (! $this->platformSettings->has(PlatformSettingsInterface::PLATFORM_IDENTITY)) {
            return null;
        }

        try {
            $identity = $this->platformSettings->get(PlatformSettingsInterface::PLATFORM_IDENTITY);

            return is_array($identity) ? $identity : null;
        } catch (PlatformSettingMissing $e) {
            throw $e;
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            return null;
        }
    }

    /** Apply the store's default language and its supported locale list. */
    protected function applyLanguage(int $tenantId): string
    {
        $languages = $this->coreGateway->getActiveLanguages();
        $enabledIds = $this->selectedIds(
            fn (): array => $this->storeSelections->enabledLanguageIds($tenantId),
        );

        if ($enabledIds === []) {
            $enabledIds = $languages
                ->pluck(LanguageSchema::ID)
                ->map(static fn ($id): int => (int) $id)
                ->all();
        }

        $codes = $languages
            ->whereIn(LanguageSchema::ID, $enabledIds)
            ->pluck(LanguageSchema::CODE)
            ->map(static fn ($code): string => (string) $code)
            ->all();

        $supported = $codes !== []
            ? $codes
            : array_values(array_unique(['en', (string) config('app.locale', 'en')]));

        SupportedLocales::useCodes($supported);

        $defaultId = $this->settingValue(SettingNameEnum::LANGUAGE_ID);
        $defaultCode = null;

        if ($defaultId !== null) {
            $language = $languages->firstWhere(LanguageSchema::ID, (int) $defaultId);
            $defaultCode = $language?->{LanguageSchema::CODE};
        }

        if ($defaultCode === null) {
            $defaultCode = $this->coreGateway->getDefaultLanguage()?->code;
        }

        $locale = (string) ($defaultCode ?? config('app.locale', 'en'));

        config(['app.locale' => $locale]);

        return $locale;
    }

    /** Apply the store's enabled currencies and default display currency. */
    protected function applyCurrencies(int $tenantId): void
    {
        $activeIds = $this->coreGateway->getActiveCurrencies()
            ->pluck(CurrencySchema::ID)
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $enabledIds = $this->selectedIds(
            fn (): array => $this->storeSelections->enabledCurrencyIds($tenantId),
        );

        if ($enabledIds === []) {
            $enabledIds = $activeIds;
        }

        $defaultId = $this->settingValue(SettingNameEnum::CURRENCY_ID);

        config([
            'store.enabled_currency_ids' => $enabledIds,
            'store.default_currency_id' => $defaultId === null ? null : (int) $defaultId,
        ]);
    }

    protected function settingValue(SettingNameEnum $name): ?string
    {
        try {
            $value = Setting::query()
                ->where(SettingSchema::NAME, $name->value)
                ->value(SettingSchema::VALUE);

            return is_string($value) ? $value : null;
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * A store's language/currency selection is optional: stores with no
     * explicit rows use the active reference-data list. Infrastructure
     * failures fall back the same way; tenancy failures never do.
     *
     * @return list<int>
     */
    protected function selectedIds(callable $query): array
    {
        try {
            /** @var list<int> $ids */
            $ids = $query();

            return $ids;
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            return [];
        }
    }
}
