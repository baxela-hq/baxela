<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Core\Exceptions\CentralOperationException;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Support\Tenancy\CentralOperationRegistry;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Tests\Feature\HelperTrait;
use Modules\Tenant\Tests\Fixtures\ProbeCentralCaller;
use Modules\Tenant\Tests\Fixtures\ProbeOperation;
use Modules\Tenant\Tests\Fixtures\RecordsContextJob;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    RecordsContextJob::flush();
    ProbeCentralCaller::flush();
});

it('runs a synchronous job under the dispatching tenant and leaves the request context intact', function () {
    $acme = $this->createTenant('acme');
    $context = app(TenantContext::class);
    $context->forTenant((int) $acme->getKey());

    dispatch(new RecordsContextJob('inline'));

    expect(RecordsContextJob::$observed)->toBe([[
        'label' => 'inline',
        'tenant_id' => (int) $acme->getKey(),
        'mode' => TenantContext::MODE_TENANT,
    ]])
        // The request keeps its own tenant after the job.
        ->and($context->mode())->toBe(TenantContext::MODE_TENANT)
        ->and($context->tenantId())->toBe((int) $acme->getKey());
});

it('unwinds nested synchronous jobs pinned to different tenants in order', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');
    $context = app(TenantContext::class);
    $context->forTenant((int) $acme->getKey());

    dispatch(new RecordsContextJob('outer', nestedTenantId: (int) $other->getKey()));

    expect(array_column(RecordsContextJob::$observed, 'label'))->toBe(['outer', 'inner'])
        ->and(array_column(RecordsContextJob::$observed, 'tenant_id'))->toBe([(int) $acme->getKey(), (int) $other->getKey()])
        ->and($context->tenantId())->toBe((int) $acme->getKey());
});

it('fails a job that touches a store-scoped model without a tenant, and restores the state after', function () {
    $context = app(TenantContext::class);
    $context->reset();

    expect(fn () => dispatch(new RecordsContextJob('orphan', touchScopedModel: true)))
        ->toThrow(TenantContextMissingException::class)
        ->and($context->mode())->toBe(TenantContext::MODE_INACTIVE);
});

it('stamps every payload with the tenant and no central grant', function () {
    config(['queue.default' => 'database']);
    $acme = $this->createTenant('acme');
    $context = app(TenantContext::class);
    $context->forTenant((int) $acme->getKey());

    dispatch(new RecordsContextJob('queued'));

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    expect($payload['tenant_id'])->toBe((int) $acme->getKey())
        ->and($payload['central_operation'])->toBeNull();
});

it('runs a real worker under the payload tenant and restores the between-jobs state', function () {
    config(['queue.default' => 'database']);
    $acme = $this->createTenant('acme');
    $context = app(TenantContext::class);

    $context->forTenant((int) $acme->getKey());
    dispatch(new RecordsContextJob('on-worker'));

    // What a worker looks like before it picks the job up.
    $context->reset();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    expect(RecordsContextJob::$observed)->toBe([[
        'label' => 'on-worker',
        'tenant_id' => (int) $acme->getKey(),
        'mode' => TenantContext::MODE_TENANT,
    ]])
        ->and($context->mode())->toBe(TenantContext::MODE_INACTIVE);
});

it('grants no mode to a job whose payload carries a central operation', function () {
    app(CentralOperationRegistry::class)->register(ProbeOperation::class, [
        'handler' => [ProbeCentralCaller::class, 'execute'],
        'callers' => [ProbeCentralCaller::class],
        'jobs' => [RecordsContextJob::class],
    ]);

    config(['queue.default' => 'database']);
    $context = app(TenantContext::class);

    $context->runCentral(ProbeOperation::class, function (): void {
        dispatch(new RecordsContextJob('central', invoke: new ProbeOperation('from-job')));
    });

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);
    expect($payload['central_operation'])->toBe(ProbeOperation::class)
        ->and($payload['tenant_id'])->toBeNull();

    $context->reset();

    Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    // The payload granted nothing: the job body started INACTIVE, and
    // central mode existed only inside the registered operation it ran.
    expect(RecordsContextJob::$observed[0]['mode'])->toBe(TenantContext::MODE_INACTIVE)
        ->and(ProbeCentralCaller::$observed)->toBe([[
            'mode' => TenantContext::MODE_CENTRAL,
            'central_operation' => ProbeOperation::class,
        ]])
        ->and($context->mode())->toBe(TenantContext::MODE_INACTIVE);
});

it('fails closed, before the job body runs, when the job is not allowed to carry the operation', function () {
    app(CentralOperationRegistry::class)->register(ProbeOperation::class, [
        'handler' => [ProbeCentralCaller::class, 'execute'],
        'callers' => [ProbeCentralCaller::class],
        'jobs' => [],
    ]);

    $context = app(TenantContext::class);
    $acme = $this->createTenant('acme');
    $context->forTenant((int) $acme->getKey());

    expect(fn () => $context->runCentral(ProbeOperation::class, function (): void {
        dispatch(new RecordsContextJob('smuggled', invoke: new ProbeOperation('x')));
    }))->toThrow(CentralOperationException::class)
        ->and(RecordsContextJob::$observed)->toBe([])
        ->and(ProbeCentralCaller::$observed)->toBe([])
        // The enclosing tenant is what remains.
        ->and($context->tenantId())->toBe((int) $acme->getKey());
});
