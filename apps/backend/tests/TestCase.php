<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Core\Models\Language;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Payment\Support\ResolvesPaymentCredentials;

abstract class TestCase extends BaseTestCase
{
    /**
     * The default English language row, shared by every module whose
     * translation payloads resolve their `language` codes against it.
     */
    public static function defaultLanguage(): Language
    {
        return Language::query()->firstOrCreate(
            ['code' => 'en'],
            ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_default' => true, 'is_active' => true],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Payment credential resolver (marketplace P5.2): its env
        // baseline is a process-level snapshot and the encrypted
        // credential map is cached — without this reset, one test's
        // config(...) seeding (every Webhook/CreatePayment test does it)
        // would leak into the next through either. Reset BEFORE the
        // test body runs, so a test's own config seeding is honored.
        app(PaymentCredentialResolverInterface::class)->refreshEnvBaseline();
        Cache::forget(ResolvesPaymentCredentials::CACHE_KEY);

        // The tenant context is a process-level singleton (multi-tenancy
        // §2). Pest runs every test in one process, so without this reset
        // a tenant set by one test would leak into the next.
        app(TenantContext::class)->reset();
        // Phase 2: keep the legacy suite (which creates store-owned rows
        // without an explicit tenant) working by defaulting to the built-in
        // default tenant. Tests that exercise the fail-closed path deactivate
        // the context themselves (multi-tenancy §10 row 12).
        app(TenantContext::class)->forTenant(1);
    }
}
