<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Core\Models\TransferInstallation;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Schemas\TransferInstallation\TransferInstallationSchema;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;
use Modules\Core\Support\Transfer\InstallationIdentity;
use Modules\Core\Support\Transfer\TransferFile;

uses(RefreshDatabase::class);

it('generates the outbound identity lazily, exactly once', function () {
    $identity = new InstallationIdentity;

    expect(TransferInstallation::query()->count())->toBe(0);

    $uuid = $identity->current();

    expect(TransferFile::isValidSourceInstallation($uuid))->toBeTrue()
        ->and(TransferInstallation::query()->count())->toBe(1)
        // Second read adopts the persisted row — never a second identity.
        ->and($identity->current())->toBe($uuid)
        ->and(TransferInstallation::query()->count())->toBe(1);
});

it('adopts an existing identity instead of racing a concurrent boot', function () {
    $winner = (string) Str::uuid();

    // A concurrent boot won the fixed-id insert race before us.
    TransferInstallation::query()->create([
        TransferInstallationSchema::ID => TransferInstallationSchema::FIXED_ID,
        TransferInstallationSchema::UUID => $winner,
    ]);

    expect((new InstallationIdentity)->current())->toBe($winner);
});

it('rotates the outbound identity without touching inbound remap history', function () {
    $identity = new InstallationIdentity;
    $original = $identity->current();

    // Inbound history keyed by an EXTERNAL source installation (C1).
    $remap = TransferRemap::query()->create([
        TransferRemapSchema::SOURCE_INSTALLATION => (string) Str::uuid(),
        TransferRemapSchema::MODULE => 'tax',
        TransferRemapSchema::ENTITY => 'tax-classes',
        TransferRemapSchema::SOURCE_ID => 42,
        TransferRemapSchema::LOCAL_ID => 108,
    ]);

    $rotated = $identity->rotate();

    expect($rotated)->not->toBe($original)
        ->and(TransferInstallation::query()->count())->toBe(1)
        ->and($identity->current())->toBe($rotated)
        // The cloned database also holds the local row this mapping
        // points at — rotation must not invalidate it (F25).
        ->and(TransferRemap::query()->count())->toBe(1)
        ->and($remap->refresh()->{TransferRemapSchema::LOCAL_ID})->toBe(108);
});
