<?php

use Modules\Core\Support\Tenancy\TenantTableInventory;
use Modules\Tenant\Tests\Fixtures\AppCodeScanner;
use Modules\Tenant\Tests\Fixtures\TenantQueryScanner;

/*
 * §4.3 layer 1: bare `unique:` / `exists:` rules may never reach a
 * store-owned table. Installation-level tables are allowed only at a
 * registered site, with the central-table justification recorded there.
 */

it('fails bare database validation rules on store-owned tables', function () {
    $storeOwned = TenantTableInventory::storeOwned();

    $offenders = [];
    foreach (TenantQueryScanner::validationSites() as $site) {
        if (in_array($site['table'], $storeOwned, true)) {
            $offenders[] = $site['key'];
        }
    }

    expect($offenders)->toBe([]);
});

it('registers every installation-level database validation rule site', function () {
    $registered = config('core.tenancy.validation_rules', []);
    $storeOwned = TenantTableInventory::storeOwned();

    $expected = [];
    foreach (TenantQueryScanner::validationSites() as $site) {
        if (! in_array($site['table'], $storeOwned, true)) {
            $expected[$site['key']] = ['table' => $site['table'], 'kind' => $site['kind']];
        }
    }

    ksort($expected);
    $actual = $registered;
    ksort($actual);

    expect($actual)->toBe($expected);
});

it('rejects a submitted tenant id in request and DTO source', function () {
    $offenders = [];
    foreach (AppCodeScanner::files() as $file) {
        if (! preg_match('#/(Http/Requests|DTOs)/#', $file)) {
            continue;
        }

        $code = AppCodeScanner::code($file);
        if (preg_match('/[\'\"]tenant_id[\'\"]|TenantColumn::TENANT_ID/', $code) === 1
            && ! str_starts_with($file, 'Modules/Tenant/app/')) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});
