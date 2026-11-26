<?php

use Modules\Core\Exceptions\CentralOperationException;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Tenant\Tests\Fixtures\AppCodeScanner;

/*
 * The central-mode boundary, checked against the source (multi-tenancy
 * §6.5): MODE_CENTRAL is reachable only through CentralOperations, and
 * every call site of run() belongs to an operation's authorized callers.
 */

it('keeps central mode reachable only through CentralOperations', function () {
    $allowed = [
        'Modules/Core/app/Support/Tenancy/CentralOperations.php',
        'Modules/Core/app/Support/Tenancy/TenantContext.php',
        'Modules/Core/app/Models/Scopes/TenantScope.php',
        'Modules/Core/app/Models/Traits/TenantScopedTrait.php',
    ];

    $offenders = [];
    foreach (AppCodeScanner::files() as $file) {
        if (! in_array($file, $allowed, true) && preg_match('/->central\(|MODE_CENTRAL|->runCentral\(/', AppCodeScanner::code($file)) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

it('binds every CentralOperations call site to a registered, authorized caller', function () {
    $registry = app(CentralOperationRegistry::class);

    $offenders = [];
    foreach (AppCodeScanner::files() as $file) {
        if (str_starts_with($file, 'Modules/Core/app/Support/Tenancy/')) {
            continue;
        }

        $source = AppCodeScanner::code($file);

        // Registering operations is the module provider's job, not a call site.
        if (! str_contains($source, 'CentralOperations') || str_contains($source, '->register(')) {
            continue;
        }

        preg_match_all('/new\s+([A-Za-z\\\\]+Operation)\b/', $source, $constructed);

        // A file that names the boundary must use it, and only by running a
        // registered operation it constructs itself.
        if (! str_contains($source, '->run(') || $constructed[1] === []) {
            $offenders[] = $file.' (references CentralOperations without running a registered operation)';

            continue;
        }

        $imported = AppCodeScanner::imports($source);
        $caller = AppCodeScanner::classOf($file);

        foreach ($constructed[1] as $name) {
            $operation = str_starts_with($name, '\\') ? substr($name, 1) : ($imported[$name] ?? $name);

            try {
                $definition = $registry->definition($operation);
            } catch (CentralOperationException) {
                $offenders[] = $file." (unregistered operation {$operation})";

                continue;
            }

            if (! in_array($caller, $definition['callers'], true)) {
                $offenders[] = $file." (not an authorized caller of {$operation})";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('lets a registered handler be invoked only by the registry', function () {
    $offenders = [];
    foreach (AppCodeScanner::files() as $file) {
        if (str_starts_with($file, 'Modules/Core/app/Support/Tenancy/')) {
            continue;
        }

        // A handler is the method the registry points at; calling it
        // directly would run it in whatever mode the caller happens to be in.
        if (preg_match('/->(execute|writesThenFails|writesAndSucceeds)\(\s*(new\s+)?[A-Za-z\\\\]*Operation\b/', AppCodeScanner::code($file)) === 1) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([]);
});

it('registers every platform operation with an existing handler and its Action as the only caller', function () {
    $registry = app(CentralOperationRegistry::class);

    $tenantOperations = array_filter(
        $registry->all(),
        fn (array $definition, string $operation): bool => str_starts_with($operation, 'Modules\\Tenant\\Operations\\'),
        ARRAY_FILTER_USE_BOTH,
    );

    expect(array_keys($tenantOperations))->toHaveCount(6);

    foreach ($tenantOperations as $operation => $definition) {
        [$handler, $method] = $definition['handler'];

        expect(method_exists($handler, $method))->toBeTrue()
            ->and($definition['callers'])->toBe([$handler])
            ->and($definition['jobs'])->toBe([]);
    }
});
