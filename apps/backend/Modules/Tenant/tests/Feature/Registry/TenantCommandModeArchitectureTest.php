<?php

use Modules\Core\Actions\Tenancy\AuditTenancyAction;
use Modules\Core\Console\Commands\TenancyAuditCommand;
use Modules\Core\Operations\AuditTenancyOperation;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Tenant\Tests\Fixtures\AppCodeScanner;

/*
 * §8: `tenancy:audit` is a platform Category-1 operation and must run
 * through a registered CentralOperations handler, never against a
 * whatever-mode-the-console-happens-to-have context.
 */

it('declares the audit command as a central operation', function () {
    expect(config('core.tenancy.command_modes'))->toBe(['tenancy:audit' => 'central']);

    $code = AppCodeScanner::code('Modules/Core/app/Console/Commands/TenancyAuditCommand.php');

    expect($code)->toContain('CentralOperations')
        ->toContain('AuditTenancyOperation')
        ->toContain('->run(');
    expect(str_contains($code, '$audit->run('))->toBeFalse();
});

it('declares a run mode for every command that enters central mode', function () {
    $modes = config('core.tenancy.command_modes', []);

    foreach (AppCodeScanner::files() as $file) {
        if (! str_contains($file, '/Console/Commands/')) {
            continue;
        }

        $code = AppCodeScanner::code($file);
        $signature = [];
        $command = preg_match('/protected \$signature\s*=\s*[\'"]([^\'"\s]+)/', $code, $signature) === 1
            ? (string) $signature[1]
            : null;

        if ($command === null) {
            continue;
        }

        $usesCentral = str_contains($code, 'CentralOperations');

        if ($usesCentral) {
            expect($modes[$command] ?? null)->toBe('central');
            expect(str_contains($code, '->run('))->toBeTrue();
        } else {
            expect($modes[$command] ?? null)->toBeNull();
        }
    }
});

it('registers the audit operation with the command as its only caller', function () {
    $definition = app(CentralOperationRegistry::class)
        ->definition(AuditTenancyOperation::class);

    expect(app(AuditTenancyOperation::class)->id())->toBe('core.tenancy.audit')
        ->and($definition['handler'])->toBe([AuditTenancyAction::class, 'execute'])
        ->and($definition['callers'])->toBe([TenancyAuditCommand::class])
        ->and($definition['jobs'])->toBe([]);
});
