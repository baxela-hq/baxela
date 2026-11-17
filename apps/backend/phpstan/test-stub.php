<?php

/**
 * PHPStan class stubs for Pest's fluent test proxies (baseline-cut
 * step 1).
 *
 * Pest types `$this` inside it()/test() closures as TestCall and the
 * bare `test()` helper's union includes it — but at runtime BOTH are
 * the running Laravel TestCase (or its tap proxy forwarding to it).
 * PHPStan's universalObjectCratesClasses covers property access only,
 * not method calls, so every `$this->actingAs()` / `test()->getJson()`
 * / `->baseUrl()` call in feature tests read as an undefined method
 * (~710 baseline entries).
 *
 * These stubs declare the runtime-available methods on both proxies:
 * the framework's testing concerns plus the per-module HelperTrait
 * helpers that `uses(HelperTrait::class)` mixes in. Signatures are
 * deliberately loose (mixed) — the authoritative types live in the
 * framework TestCase and the traits, which stay statically visible for
 * their direct call forms. PHPStan never executes stubs; this file is
 * analysis-only.
 */


namespace Pest\PendingCalls {

    /**
 * @method mixed actingAs(mixed ...$args)
 * @method mixed getJson(string $uri, array<string, string> $headers = [])
 * @method mixed postJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed patchJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed putJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed deleteJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed get(string $uri, array<string, string> $headers = [])
 * @method mixed post(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method mixed artisan(mixed ...$args)
 * @method mixed instance(mixed ...$args)
 * @method mixed mock(mixed ...$args)
 * @method mixed spy(mixed ...$args)
 * @method mixed seed(mixed ...$args)
 * @method mixed travel(mixed ...$args)
 * @method mixed fail(string $message = '')
 * @method mixed assertDatabaseHas(mixed ...$args)
 * @method mixed assertDatabaseMissing(mixed ...$args)
 * @method mixed expectsDatabaseQueryCount(mixed ...$args)
 * @method string baseUrl(string $endpoint)
 * @method string adminUrl(string $endpoint)
 * @method mixed superAdminUser()
 * @method mixed adminUser()
 * @method mixed superAdminRole(mixed ...$args)
 * @method mixed variantWithStock(mixed ...$args)
 * @method mixed transferFile(mixed ...$args)
 * @method mixed taxRate(mixed ...$args)
 * @method mixed taxClass(mixed ...$args)
 * @method mixed defaultTaxClass(mixed ...$args)
 * @method mixed shippingMethodForCountry(mixed ...$args)
 * @method mixed addressForUser(mixed ...$args)
 * @method mixed commentableProduct(mixed ...$args)
 * @method mixed seededProduct(mixed ...$args)
 * @method mixed ratedProduct(mixed ...$args)
 * @method mixed orderFor(mixed ...$args)
 * @method mixed paidOrder(mixed ...$args)
 * @method mixed stockFor(mixed ...$args)
 * @method mixed actingAs(mixed ...$args)
 * @method mixed getJson(string $uri, array<string, string> $headers = [])
 * @method mixed postJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed patchJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed putJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed deleteJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed get(string $uri, array<string, string> $headers = [])
 * @method mixed post(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method mixed artisan(mixed ...$args)
 * @method mixed instance(mixed ...$args)
 * @method mixed mock(mixed ...$args)
 * @method mixed spy(mixed ...$args)
 * @method mixed seed(mixed ...$args)
 * @method mixed travel(mixed ...$args)
 * @method mixed fail(string $message = '')
 * @method mixed assertDatabaseHas(mixed ...$args)
 * @method mixed assertDatabaseMissing(mixed ...$args)
 * @method mixed expectsDatabaseQueryCount(mixed ...$args)
 * @method string baseUrl(string $endpoint)
 * @method string adminUrl(string $endpoint)
 * @method mixed superAdminUser()
 * @method mixed adminUser()
 * @method mixed superAdminRole(mixed ...$args)
 * @method mixed variantWithStock(mixed ...$args)
 * @method mixed transferFile(mixed ...$args)
 * @method mixed taxRate(mixed ...$args)
 * @method mixed taxClass(mixed ...$args)
 * @method mixed defaultTaxClass(mixed ...$args)
 * @method mixed shippingMethodForCountry(mixed ...$args)
 * @method mixed addressForUser(mixed ...$args)
 * @method mixed commentableProduct(mixed ...$args)
 * @method mixed seededProduct(mixed ...$args)
 * @method mixed ratedProduct(mixed ...$args)
 * @method mixed orderFor(mixed ...$args)
 * @method mixed paidOrder(mixed ...$args)
 * @method mixed stockFor(mixed ...$args)
     */
    class TestCall
    {
    }
}

namespace Pest\Support {

    /**
     *      * The tap proxy forwards every call to the running TestCase — the
     * other union arm of Pest's test() helper. Same runtime-available
     * method set as the TestCall stub.
 * @method mixed actingAs(mixed ...$args)
 * @method mixed getJson(string $uri, array<string, string> $headers = [])
 * @method mixed postJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed patchJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed putJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed deleteJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed get(string $uri, array<string, string> $headers = [])
 * @method mixed post(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method mixed artisan(mixed ...$args)
 * @method mixed instance(mixed ...$args)
 * @method mixed mock(mixed ...$args)
 * @method mixed spy(mixed ...$args)
 * @method mixed seed(mixed ...$args)
 * @method mixed travel(mixed ...$args)
 * @method mixed fail(string $message = '')
 * @method mixed assertDatabaseHas(mixed ...$args)
 * @method mixed assertDatabaseMissing(mixed ...$args)
 * @method mixed expectsDatabaseQueryCount(mixed ...$args)
 * @method string baseUrl(string $endpoint)
 * @method string adminUrl(string $endpoint)
 * @method mixed superAdminUser()
 * @method mixed adminUser()
 * @method mixed superAdminRole(mixed ...$args)
 * @method mixed variantWithStock(mixed ...$args)
 * @method mixed transferFile(mixed ...$args)
 * @method mixed taxRate(mixed ...$args)
 * @method mixed taxClass(mixed ...$args)
 * @method mixed defaultTaxClass(mixed ...$args)
 * @method mixed shippingMethodForCountry(mixed ...$args)
 * @method mixed addressForUser(mixed ...$args)
 * @method mixed commentableProduct(mixed ...$args)
 * @method mixed seededProduct(mixed ...$args)
 * @method mixed ratedProduct(mixed ...$args)
 * @method mixed orderFor(mixed ...$args)
 * @method mixed paidOrder(mixed ...$args)
 * @method mixed stockFor(mixed ...$args)
 * @method mixed actingAs(mixed ...$args)
 * @method mixed getJson(string $uri, array<string, string> $headers = [])
 * @method mixed postJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed patchJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed putJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed deleteJson(string $uri, mixed $data = [], array<string, string> $headers = [])
 * @method mixed get(string $uri, array<string, string> $headers = [])
 * @method mixed post(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method mixed artisan(mixed ...$args)
 * @method mixed instance(mixed ...$args)
 * @method mixed mock(mixed ...$args)
 * @method mixed spy(mixed ...$args)
 * @method mixed seed(mixed ...$args)
 * @method mixed travel(mixed ...$args)
 * @method mixed fail(string $message = '')
 * @method mixed assertDatabaseHas(mixed ...$args)
 * @method mixed assertDatabaseMissing(mixed ...$args)
 * @method mixed expectsDatabaseQueryCount(mixed ...$args)
 * @method string baseUrl(string $endpoint)
 * @method string adminUrl(string $endpoint)
 * @method mixed superAdminUser()
 * @method mixed adminUser()
 * @method mixed superAdminRole(mixed ...$args)
 * @method mixed variantWithStock(mixed ...$args)
 * @method mixed transferFile(mixed ...$args)
 * @method mixed taxRate(mixed ...$args)
 * @method mixed taxClass(mixed ...$args)
 * @method mixed defaultTaxClass(mixed ...$args)
 * @method mixed shippingMethodForCountry(mixed ...$args)
 * @method mixed addressForUser(mixed ...$args)
 * @method mixed commentableProduct(mixed ...$args)
 * @method mixed seededProduct(mixed ...$args)
 * @method mixed ratedProduct(mixed ...$args)
 * @method mixed orderFor(mixed ...$args)
 * @method mixed paidOrder(mixed ...$args)
 * @method mixed stockFor(mixed ...$args)
     */
    final class HigherOrderTapProxy
    {
    }
}
