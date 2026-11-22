<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferGuardRegistry;
use Modules\Core\Support\Transfer\TransferImportSession;
use Modules\Core\Tests\Feature\HelperTransferTrait;
use Modules\Tax\Transfer\TaxTransfer;

uses(HelperTransferTrait::class);

/**
 * Import-lease semantics (C7/F26/F28, postgres-migration §3.1) against the
 * real ModuleTransferRunner. Driver-agnostic since the named-lock
 * replacement: the lease is a table row, so these run on the SQLite plane
 * and on every engine the suite targets. Genuine multi-connection races
 * (FOR UPDATE blocking, clock_timestamp pining) are exercised on real
 * MySQL/PostgreSQL servers in Phase D of the postgres migration.
 *
 * A per-test FILE database (not :memory:): the session-loss test's lazy
 * reconnect must land on the SAME schema for the control-plane audit
 * write to survive (F30) — a replacement :memory: PDO would be an empty
 * database, which is a MySQL-test-era artifact, not the protocol.
 *
 *   php artisan test --filter=TransferImportLockTest
 */
beforeEach(function () {
    $this->transferLockDb = storage_path('framework/testing/'.Str::uuid()->toString().'.sqlite');
    @mkdir(dirname($this->transferLockDb), recursive: true);

    config(['database.connections.'.config('database.default').'.database' => $this->transferLockDb]);
    DB::purge();

    $this->artisan('migrate', ['--force' => true]);
});

afterEach(function () {
    DB::purge();
    @unlink($this->transferLockDb);
});
it('rejects an import whose destination-module lease is held past the wait budget (F26)', function () {
    // A foreign, LIVE lease on the tax module — same destination module
    // serializes, whatever the source installation (F26).
    DB::table('core_transfer_locks')->insert([
        'name' => 'transfer:import:tax',
        'owner_token' => '11111111-2222-3333-4444-555555555555',
        'generation' => 3,
        'heartbeat_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    config(['core.transfer.lock_timeout' => 0]);

    $runner = app(ModuleTransferRunner::class);

    try {
        $runner->run(
            'transfer-tax.json',
            $this->taxTransferFileContent((string) Str::uuid()),
            TransferStrategyEnum::UPDATE,
            false,
            'tax',
            app(TaxTransfer::class),
        );

        $this->fail('The contended import should have failed with import_in_progress.');
    } catch (ImportFailedException $e) {
        expect($e->meta['reason'] ?? null)->toBe('import_in_progress');
    }
});

it('keeps ownership across a between-units connection swap (row-state authority)', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $kept = false;

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function ($context) use (&$kept): void {
        // Ownership is the lease ROW, not the session: a disconnect BETWEEN
        // units (no unit open) no longer loses it. The file-backed test
        // database makes the lazy reconnect land on the SAME schema, so the
        // probe re-reads the live row — the named-lock era treated any
        // session swap as loss; the lease row does not.
        DB::connection()->disconnect();

        try {
            $context->assertOwnership();
            $kept = true;
        } catch (TransferLockSessionLostException) {
            $kept = false;
        }
    });

    expect($kept)->toBeTrue();
});

it('lets a second import reclaim the expired lease after release (F26)', function () {
    // The audit row attributes the run to the acting admin.
    $this->actingAs($this->superAdminUser());

    config(['core.transfer.lock_timeout' => 1]);

    $summary = app(ModuleTransferRunner::class)->run(
        'transfer-tax.json',
        $this->taxTransferFileContent((string) Str::uuid()),
        TransferStrategyEnum::UPDATE,
        false,
        'tax',
        app(TaxTransfer::class),
    );

    expect($summary['status'])->toBe('completed');

    // Teardown expired the lease (token+generation-guarded release), so the
    // next import reclaims rather than contends.
    $summaryTwo = app(ModuleTransferRunner::class)->run(
        'transfer-tax.json',
        $this->taxTransferFileContent((string) Str::uuid()),
        TransferStrategyEnum::UPDATE,
        false,
        'tax',
        app(TaxTransfer::class),
    );

    $row = DB::table('core_transfer_locks')->where('name', 'transfer:import:tax')->first();

    expect($summaryTwo['status'])->toBe('completed')
        // Reclamation is the only generation advance (I3): exactly one bump.
        ->and((int) $row->generation)->toBe(2);
});

it('audits a session loss as a failed run with the failure reason (F28)', function () {
    // The audit row attributes the run to the acting admin.
    $this->actingAs($this->superAdminUser());

    $connection = DB::connection();

    // One-shot: the importer's row WRITE drops the session mid-run — after
    // the lease is acquired (hook invocation must not fire before the run
    // even holds it). The write unit's session check then fails on the
    // replacement PDO, and the run aborts instead of continuing on a
    // session whose server-side transaction is already gone (F28/F29).
    $fired = false;
    $connection->beforeExecuting(function (string $query) use ($connection, &$fired): void {
        if ($fired || ! str_contains(strtolower($query), 'insert') || ! str_contains(strtolower($query), 'tax_classes')) {
            return;
        }
        $fired = true;
        $connection->disconnect();
    });

    $summary = app(ModuleTransferRunner::class)->run(
        'transfer-tax.json',
        $this->taxTransferFileContent((string) Str::uuid()),
        TransferStrategyEnum::UPDATE,
        false,
        'tax',
        app(TaxTransfer::class),
    );

    expect($summary['status'])->toBe('failed')
        ->and($summary['warnings'])->toContain('lock_session_lost');

    $audit = DB::table(TransferImportSchema::TABLE)->latest('id')->first();
    $summaryJson = json_decode((string) $audit->{TransferImportSchema::SUMMARY}, true);

    expect($summaryJson['failure_reason'] ?? null)->toBe('lock_session_lost')
        // The control-plane audit write survived the session loss (F30).
        ->and($summaryJson['source_installation'] ?? null)->toBeString()
        ->and($audit->{TransferImportSchema::STATUS})->toBe('failed');
});
