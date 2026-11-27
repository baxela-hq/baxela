<?php

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\CategoryTranslation;
use Modules\Catalog\Models\Product;
use Modules\Core\Console\Commands\TenancyAuditCommand;
use Modules\Core\Contracts\Events\Tenant\CentralOperationRunEvent;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Store-owned rows go in under the owner's context, the way the application
 * writes them. Both planted inconsistencies below use ordinary FKs, so the
 * test stays portable across SQLite and PostgreSQL.
 */
$seedGraph = static function (Tenant $alpha, Tenant $beta): array {
    $context = app(TenantContext::class);

    /** @var Category $alphaCategory */
    $alphaCategory = $context->runForTenant(
        (int) $alpha->getKey(),
        static fn (): Category => Category::factory()->create(),
    );
    /** @var Category $betaCategory */
    $betaCategory = $context->runForTenant(
        (int) $beta->getKey(),
        static fn (): Category => Category::factory()->create(),
    );
    /** @var Product $betaProduct */
    $betaProduct = $context->runForTenant(
        (int) $beta->getKey(),
        static fn (): Product => Product::factory()->create(),
    );

    $context->runForTenant((int) $alpha->getKey(), static function () use ($alphaCategory): void {
        $alphaCategory->translations()->create([
            'title' => 'Alpha category',
            'slug' => 'alpha-category',
        ]);
    });

    return [
        'alpha' => $alpha,
        'beta' => $beta,
        'alphaCategory' => $alphaCategory,
        'betaCategory' => $betaCategory,
        'betaProduct' => $betaProduct,
    ];
};

it('audits a clean two-tenant graph and records the command reason', function () use ($seedGraph) {
    Event::fake([CentralOperationRunEvent::class]);

    /** @var Tenant $alpha */
    $alpha = $this->createTenant('alpha');
    /** @var Tenant $beta */
    $beta = $this->createTenant('beta');
    $seedGraph($alpha, $beta);

    $reason = 'nightly ownership check';
    $exit = Artisan::call('tenancy:audit', ['--json' => true, '--reason' => $reason]);

    /** @var array{violations: list<array{type: string, table: string, detail: string}>, relations: int} $result */
    $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($exit)->toBe(Command::SUCCESS)
        ->and($result['violations'])->toBe([]);

    Event::assertDispatched(fn (CentralOperationRunEvent $event): bool => $event->operation_id === 'core.tenancy.audit'
        && $event->reason === $reason
        && $event->caller === TenancyAuditCommand::class);
});

it('uses a deterministic reason when none is supplied', function () {
    Event::fake([CentralOperationRunEvent::class]);

    Artisan::call('tenancy:audit', ['--json' => true]);

    Event::assertDispatched(fn (CentralOperationRunEvent $event): bool => $event->operation_id === 'core.tenancy.audit'
        && $event->reason === 'tenancy:audit command');
});

it('audits from an inactive console context with a platform audit row', function () {
    app(TenantContext::class)->deactivate();

    $exit = Artisan::call('tenancy:audit', ['--json' => true, '--reason' => 'nightly cron']);

    expect($exit)->toBe(Command::SUCCESS);

    /** @var AuditLog $audit */
    $audit = AuditLog::query()->withoutGlobalScopes()->sole();
    /** @var array<string, mixed> $properties */
    $properties = (array) $audit->{AuditLogSchema::PROPERTIES};

    expect($audit->getAttribute(TenantColumn::TENANT_ID))->toBeNull()
        ->and($properties['operation_id'])->toBe('core.tenancy.audit')
        ->and($properties['reason'])->toBe('nightly cron')
        ->and($properties['outcome'])->toBe('succeeded');
});

it('reports planted direct and pivot mismatches with a failing exit code', function () use ($seedGraph) {
    /** @var Tenant $alpha */
    $alpha = $this->createTenant('alpha');
    /** @var Tenant $beta */
    $beta = $this->createTenant('beta');
    /** @var array{alpha: Tenant, beta: Tenant, alphaCategory: Category, betaCategory: Category, betaProduct: Product} $graph */
    $graph = $seedGraph($alpha, $beta);

    // The translation carries alpha's owner but points at beta's category:
    // its FK is id-only, so the database accepts the cross-store reference.
    $translation = (new Category)->translations();
    DB::table((new CategoryTranslation)->getTable())->insert([
        TenantColumn::TENANT_ID => (int) $graph['alpha']->getKey(),
        $translation->getForeignKeyName() => (int) $graph['betaCategory']->getKey(),
        'title' => 'Stolen beta title',
        'slug' => 'stolen-beta-title',
    ]);

    // The pivot table has no tenant column; ownership comes from both ends.
    $pivot = $graph['alphaCategory']->products();
    DB::table($pivot->getTable())->insert([
        $pivot->getForeignPivotKeyName() => (int) $graph['alphaCategory']->getKey(),
        $pivot->getRelatedPivotKeyName() => (int) $graph['betaProduct']->getKey(),
    ]);

    $exit = Artisan::call('tenancy:audit', ['--json' => true]);

    /** @var array{violations: list<array{type: string, table: string, detail: string}>, relations: int} $result */
    $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    /** @var list<array{type: string, table: string, detail: string}> $violations */
    $violations = $result['violations'];

    expect($exit)->toBe(Command::FAILURE)
        ->and($violations)->toHaveCount(2)
        ->and(array_filter(
            $violations,
            static fn (array $violation): bool => $violation['type'] === 'tenant_mismatch',
        ))->toHaveCount(2);
});
