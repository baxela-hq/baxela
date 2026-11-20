<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\TransferImport;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFormat;
use Modules\Core\Tests\Feature\HelperTransferTrait;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Transfer\TaxTransfer;

uses(RefreshDatabase::class);
uses(HelperTransferTrait::class);

it('persists source-id remaps for real runs and nothing for dry runs', function () {
    $this->actingAs($this->superAdminUser());
    $source = (string) Str::uuid();

    $runner = app(ModuleTransferRunner::class);

    // Dry run: matching and counting only — the ledger stays empty (C4).
    $runner->run(
        'transfer-tax.json',
        $this->taxTransferFileContent($source),
        TransferStrategyEnum::UPDATE,
        true,
        'tax',
        app(TaxTransfer::class),
    );

    expect(TransferRemap::query()->count())->toBe(0);

    // Real run: every imported row lands in the ledger under the file's
    // source namespace (C1), with the created row's local id.
    $runner->run(
        'transfer-tax.json',
        $this->taxTransferFileContent($source),
        TransferStrategyEnum::UPDATE,
        false,
        'tax',
        app(TaxTransfer::class),
    );

    $classId = (int) TaxClass::query()->where(TaxClassSchema::NAME, 'Standard')->value('id');

    $remap = TransferRemap::query()
        ->where('source_installation', $source)
        ->where('module', 'tax')
        ->where('entity', 'tax-classes')
        ->where('source_id', 1)
        ->first();

    expect($remap)->not->toBeNull()
        ->and((int) $remap->{TransferRemapSchema::LOCAL_ID})->toBe($classId);

    // Re-import converges: the same source row maps to the SAME local row
    // (matched by natural key), the ledger upserts, nothing duplicates.
    $runner->run(
        'transfer-tax.json',
        $this->taxTransferFileContent($source),
        TransferStrategyEnum::UPDATE,
        false,
        'tax',
        app(TaxTransfer::class),
    );

    expect(TaxClass::query()->count())->toBe(1)
        ->and(TransferRemap::query()->count())->toBe(1);
});

it('records the resolution mode and raw-id diagnostics in the audit row', function () {
    $this->actingAs($this->superAdminUser());

    // A legacy file (no source_installation) importing reference data —
    // no cross-module refs here, so the run completes and the audit row
    // still carries the namespace diagnostics (C1/C2).
    $legacy = json_decode($this->taxTransferFileContent((string) Str::uuid()), true);
    unset($legacy[TransferFormat::KEY_SOURCE_INSTALLATION]);

    $summary = app(ModuleTransferRunner::class)->run(
        'transfer-tax.json',
        json_encode($legacy),
        TransferStrategyEnum::UPDATE,
        false,
        'tax',
        app(TaxTransfer::class),
    );

    expect($summary['status'])->toBe('completed')
        ->and($summary['raw_id_resolutions'])->toBe(0);

    $audit = TransferImport::query()->latest('id')->first();
    $auditSummary = $audit->summary;

    expect($auditSummary)->toHaveKey('source_installation')
        ->and($auditSummary['source_installation'])->toBeNull()
        ->and($auditSummary['raw_id_resolutions'])->toBe(0);
});
