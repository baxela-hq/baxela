<?php

use Modules\Tenant\Tests\Fixtures\TenantQueryScanner;

/*
 * §4.3 layer 0: every query-builder chain on a store-owned table is a
 * registered site. The allowlist is per site (not per file), so adding a
 * chain to an already-registered file still fails the expected-set check.
 */

it('registers every raw query-builder site on a store-owned table', function () {
    $registered = config('core.tenancy.query_sites', []);

    $expected = [];
    foreach (TenantQueryScanner::querySites() as $site) {
        if (TenantQueryScanner::isRelevantTable($site['table'])) {
            $expected[$site['key']] = ['table' => $site['table'], 'operation' => $site['operation']];
        }
    }

    ksort($expected);
    $actual = array_map(
        static fn (array $entry): array => ['table' => $entry['table'], 'operation' => $entry['operation']],
        $registered,
    );
    ksort($actual);

    expect($actual)->toBe($expected);
});

it('keeps every registered raw site tenant-guarded or centrally justified', function () {
    $sites = [];
    foreach (TenantQueryScanner::querySites() as $site) {
        $sites[$site['key']] = $site;
    }

    foreach (config('core.tenancy.query_sites', []) as $key => $entry) {
        expect($entry)->toHaveKeys(['table', 'operation', 'justification'])
            ->and($sites[$key] ?? null)->not->toBeNull();

        // A site the scanner finds guarded may not hide behind a generic
        // "central" justification, and a genuinely central site may not
        // claim a tenant predicate it does not have.
        $justification = $entry['justification'];
        $writes = ['insert', 'insertOrIgnore', 'insertGetId', 'upsert', 'updateOrInsert', 'update', 'delete'];
        if ($sites[$key]['guard'] === 'stamp' && in_array($entry['operation'], $writes, true)) {
            expect($justification)->toBe('tenant-stamped write (§4.3 layer 0)');
        } elseif ($sites[$key]['guard'] !== null) {
            expect($justification)->toStartWith('tenant predicate via');
        } else {
            expect($justification)->toStartWith('central operation:');
        }
    }
});
