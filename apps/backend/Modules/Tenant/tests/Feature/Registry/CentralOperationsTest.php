<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Core\Exceptions\CentralOperationException;
use Modules\Core\Support\Tenancy\CentralOperation;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Core\Support\Tenancy\CentralOperations;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;
use Modules\Tenant\Tests\Fixtures\FailingProbeOperation;
use Modules\Tenant\Tests\Fixtures\ProbeCentralCaller;
use Modules\Tenant\Tests\Fixtures\ProbeOperation;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    ProbeCentralCaller::flush();
    app(CentralOperationRegistry::class)->register(ProbeOperation::class, [
        'handler' => [ProbeCentralCaller::class, 'execute'],
        'callers' => [ProbeCentralCaller::class],
        'jobs' => [],
    ]);
});

it('runs a registered operation from its authorized caller in central mode and audits it', function () {
    $acme = $this->createTenant('acme');
    $context = app(TenantContext::class);
    $context->forTenant((int) $acme->getKey());

    $result = app(ProbeCentralCaller::class)->run(new ProbeOperation('probe'), 'reviewing a cross-store report');

    expect($result)->toBe(ProbeOperation::RESULT)
        ->and(ProbeCentralCaller::$observed)->toBe([[
            'mode' => TenantContext::MODE_CENTRAL,
            'central_operation' => ProbeOperation::class,
        ]])
        // The enclosing request is exactly what remains.
        ->and($context->mode())->toBe(TenantContext::MODE_TENANT)
        ->and($context->tenantId())->toBe((int) $acme->getKey());

    $properties = (array) AuditLog::query()->sole()->{AuditLogSchema::PROPERTIES};

    expect($properties['operation'])->toBe(ProbeOperation::class)
        ->and($properties['reason'])->toBe('reviewing a cross-store report')
        ->and($properties['caller'])->toBe(ProbeCentralCaller::class)
        ->and($properties['outcome'])->toBe('succeeded');
});

it('refuses an operation invoked from anywhere but its authorized caller', function () {
    $acme = $this->createTenant('acme');
    $context = app(TenantContext::class);
    $context->forTenant((int) $acme->getKey());

    Log::shouldReceive('warning')->once()->withArgs(
        fn (string $message): bool => $message === 'Central operation refused',
    );

    $this->assertThrowsCode(
        fn () => app(CentralOperations::class)->run(new ProbeOperation('probe'), 'from the test'),
        CentralOperationException::class,
        'core.tenant.operation_not_allowed',
    );

    expect(ProbeCentralCaller::$observed)->toBe([])
        ->and($context->tenantId())->toBe((int) $acme->getKey())
        // Nothing ran, so nothing is audited — but the attempt is logged.
        ->and(AuditLog::query()->count())->toBe(0);
});

it('refuses an unregistered operation, even one echoing a registered label', function () {
    $lookAlike = new class implements CentralOperation
    {
        public function id(): string
        {
            return 'tenant.probe';
        }
    };

    $this->assertThrowsCode(
        fn () => app(CentralOperations::class)->run($lookAlike, 'reason'),
        CentralOperationException::class,
        'core.tenant.operation_unregistered',
    );

    expect(ProbeCentralCaller::$observed)->toBe([]);
});

it('requires a reason', function () {
    $this->assertThrowsCode(
        fn () => app(ProbeCentralCaller::class)->run(new ProbeOperation('probe'), '   '),
        CentralOperationException::class,
        'core.tenant.reason_required',
    );

    expect(ProbeCentralCaller::$observed)->toBe([])
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rolls the operation back when its handler fails, and records the failed attempt', function () {
    app(CentralOperationRegistry::class)->register(FailingProbeOperation::class, [
        'handler' => [ProbeCentralCaller::class, 'writesThenFails'],
        'callers' => [ProbeCentralCaller::class],
        'jobs' => [],
    ]);

    expect(fn () => app(ProbeCentralCaller::class)->run(new FailingProbeOperation, 'reason'))
        ->toThrow(RuntimeException::class)
        ->and(Tenant::query()->count())->toBe(0);

    $properties = (array) AuditLog::query()->sole()->{AuditLogSchema::PROPERTIES};

    expect($properties['outcome'])->toBe('failed')
        ->and($properties['reason'])->toBe('reason');
});

it('never lets an operation take effect without its audit record', function () {
    app(CentralOperationRegistry::class)->register(FailingProbeOperation::class, [
        'handler' => [ProbeCentralCaller::class, 'writesAndSucceeds'],
        'callers' => [ProbeCentralCaller::class],
        'jobs' => [],
    ]);
    Schema::dropIfExists(AuditLogSchema::TABLE);

    // The handler itself succeeds; what fails is the audit record that is
    // its precondition — and the store it wrote is rolled back with it.
    expect(fn () => app(ProbeCentralCaller::class)->run(new FailingProbeOperation, 'reason'))
        ->toThrow(QueryException::class)
        ->and(Tenant::query()->count())->toBe(0);
});

it('commits an operation together with its audit record', function () {
    app(CentralOperationRegistry::class)->register(FailingProbeOperation::class, [
        'handler' => [ProbeCentralCaller::class, 'writesAndSucceeds'],
        'callers' => [ProbeCentralCaller::class],
        'jobs' => [],
    ]);

    $result = app(ProbeCentralCaller::class)->run(new FailingProbeOperation, 'reason');

    expect($result)->toBe('written')
        ->and(Tenant::query()->where(TenantSchema::SLUG, 'written-by-operation')->exists())->toBeTrue()
        ->and(AuditLog::query()->count())->toBe(1);
});

it('validates the registry entries', function () {
    $registry = app(CentralOperationRegistry::class);

    // The Tenant module registers its platform operations at boot.
    expect(count($registry->all()))->toBeGreaterThan(0);

    $this->assertThrowsCode(
        fn () => $registry->register(ProbeOperation::class, [
            'handler' => [ProbeCentralCaller::class, 'execute'],
            'callers' => [ProbeCentralCaller::class],
            'jobs' => [],
        ]),
        CentralOperationException::class,
        'core.tenant.operation_already_registered',
    );

    $anonymous = new class implements CentralOperation
    {
        public function id(): string
        {
            return 'tenant.anonymous';
        }
    };

    // A handler method that does not exist…
    $this->assertThrowsCode(
        fn () => $registry->register($anonymous::class, [
            'handler' => [ProbeCentralCaller::class, 'noSuchMethod'],
            'callers' => [ProbeCentralCaller::class],
            'jobs' => [],
        ]),
        CentralOperationException::class,
        'core.tenant.operation_invalid_definition',
    );

    // …and an entry no class is allowed to call.
    $this->assertThrowsCode(
        fn () => $registry->register($anonymous::class, [
            'handler' => [ProbeCentralCaller::class, 'execute'],
            'callers' => [],
            'jobs' => [],
        ]),
        CentralOperationException::class,
        'core.tenant.operation_invalid_definition',
    );
});
