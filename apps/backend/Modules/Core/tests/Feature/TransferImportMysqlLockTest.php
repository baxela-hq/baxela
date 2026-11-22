<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Schemas\TransferImport\TransferImportSchema;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Tests\Feature\HelperTransferTrait;
use Modules\Tax\Transfer\TaxTransfer;

uses(RefreshDatabase::class);
uses(HelperTransferTrait::class);

/**
 * Named-lock semantics (C7/F26/F28) need a real MySQL server: the SQLite
 * test plane has no GET_LOCK/IS_USED_LOCK, so these tests skip there and
 * are meant to be run explicitly against MySQL:
 *
 *   DB_CONNECTION=mysql DB_DATABASE=... php artisan test --filter=TransferImportMysqlLockTest
 *
 * SQLite-plane equivalents of the session/guard semantics live in
 * TransferImportSessionTest (PDO-instance identity probe).
 */
it('rejects an import whose destination-module lock is held past the wait budget (F26)', function () {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Named-lock semantics need a MySQL test connection.');
    }

    // A DIFFERENT session (not the test's default connection) holds the
    // tax module's lock — same-session GET_LOCK is re-entrant and would
    // never contend.
    $default = config('database.default');
    config(['database.connections.transfer_lock_probe' => config("database.connections.{$default}")]);
    $probe = DB::connection('transfer_lock_probe');
    $probe->selectOne('SELECT GET_LOCK(?, 0) AS acquired', ['transfer:import:tax']);

    config(['core.transfer.lock_timeout' => 1]);

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
    } finally {
        $probe->selectOne('SELECT RELEASE_LOCK(?) AS released', ['transfer:import:tax']);
    }
});

it('lets a second import acquire the destination-module lock after release (F26)', function () {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Named-lock semantics need a MySQL test connection.');
    }

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
});

it('audits a session loss as a failed run with the failure reason (F28)', function () {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Named-lock semantics need a MySQL test connection.');
    }

    // The audit row attributes the run to the acting admin.
    $this->actingAs($this->superAdminUser());

    $connection = DB::connection();

    // One-shot: the importer's row WRITE drops the session mid-run — after
    // GET_LOCK/IS_USED_LOCK (hook invocation must not fire before the lock
    // is even held). The ownership hook then fails its IS_USED_LOCK probe
    // on the replacement session, and the run aborts instead of continuing
    // on a session that does not own the destination-module lock (F28/F29).
    $fired = false;
    $connection->beforeExecuting(function (string $query) use ($connection, &$fired): void {
        if ($fired || ! str_contains(strtolower($query), 'insert into `tax_classes`')) {
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
