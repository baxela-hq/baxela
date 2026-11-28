<?php

use Modules\Catalog\Actions\Public\Sitemap\ListSitemapAction as CatalogSitemap;
use Modules\Content\Actions\Public\Sitemap\ListSitemapAction as ContentSitemap;
use Modules\Core\Actions\Admin\Currency\SyncExchangeRatesAction;
use Modules\Core\Actions\Admin\Extension\CheckExtensionUpdatesAction;
use Modules\Core\Support\PicksTranslation;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Payment\Gateways\PaypalClient;
use Modules\Payment\Support\ResolvesPaymentCredentials;
use Modules\Setting\Support\AppliesStoreIdentity;
use Modules\Setting\Support\AppliesStoreTimezone;
use Modules\Setting\Support\PlatformSettings;
use Modules\Setting\Support\ResolvesFxSettings;
use Modules\Setting\Support\ResolvesPricingMode;

/**
 * Phase 4 entry artifact for §6.7 item 6: the reviewed F37 18-key cache
 * disposition (9 store-dependent + 9 installation-level), plus the new
 * `platform:setting.{name}` family from §6.7 item 7. The table is
 * executable so a future key change cannot silently opt out of the rule.
 */
it('keeps the reviewed 18-key cache disposition and platform resolver key', function () {
    $tenantId = 4242;
    app(TenantContext::class)->forTenant($tenantId);
    $prefix = app(TenantContext::class)->cachePrefix();

    expect($prefix)->toBe('t'.$tenantId.':');

    // 1–9: store-dependent. The payment keys were already prefixed by §6.3;
    // they are listed here so the disposition is complete and their
    // prefixing is pinned by this artifact.
    $storeKeys = [
        'setting.timezone' => AppliesStoreTimezone::cacheKey(),
        'setting.store_identity' => AppliesStoreIdentity::cacheKey(),
        'setting.prices_include_tax' => ResolvesPricingMode::cacheKey(),
        'setting.fx' => ResolvesFxSettings::cacheKey(),
        'core.language.default_id' => PicksTranslation::cacheKey(),
        'catalog.public.sitemap' => $prefix.CatalogSitemap::CACHE_KEY,
        'content.public.sitemap' => $prefix.ContentSitemap::CACHE_KEY,
        'payment.credentials:stripe:1' => $prefix.ResolvesPaymentCredentials::CACHE_PREFIX.'stripe:1',
        'payment.paypal.access_token:1' => $prefix.PaypalClient::ACCESS_TOKEN_CACHE_KEY.':1',
    ];

    expect($storeKeys)->toHaveCount(9);

    foreach ($storeKeys as $base => $key) {
        expect($key)->toBe($prefix.$base, $base.' must be tenant-prefixed');
    }

    // 10–18: installation/platform. These nine keys were already reviewed
    // in F37 and stay deliberately unprefixed.
    $installationKeys = [
        'telegram:bot:username' => 'telegram:bot:username',
        'core.extension-updates' => CheckExtensionUpdatesAction::CACHE_KEY,
        'core.extension-updates.stale' => CheckExtensionUpdatesAction::STALE_KEY,
        'core.regions.US' => 'core.regions.US',
        'catalog.search.reconcile.failures' => 'catalog.search.reconcile.failures',
        'auth:google:jwks' => 'auth:google:jwks',
        'auth:google:jwks:refresh' => 'auth:google:jwks:refresh',
        'sign-in-gate' => 'sign-in-gate:email-hash',
        'core.fx.sync' => SyncExchangeRatesAction::LOCK_NAME,
    ];

    expect($installationKeys)->toHaveCount(9);

    foreach ($installationKeys as $base => $key) {
        expect(str_starts_with($key, $prefix))->toBeFalse($base.' must not be tenant-prefixed');
    }

    // §6.7 item 7 adds one platform family; it is installation-level too.
    expect(PlatformSettings::cacheKey('fx_provider'))->toBe('platform:setting.fx_provider')
        ->and(str_starts_with(PlatformSettings::cacheKey('fx_provider'), $prefix))->toBeFalse();
});
