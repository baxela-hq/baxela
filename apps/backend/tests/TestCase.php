<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Core\Models\Language;
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
    }
}
