<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFormat;
use Modules\Core\Utils\Auth;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Tests\Feature\HelperTrait;
use Modules\Discount\Transfer\DiscountTransfer;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Redemption import (C5 two-pass restore): pass 1 skips order-linked rows
 * with an EXPECTED warning; pass 2 (after Order import) resolves them via
 * the ledger; usage_count recomputes from the LOCAL dataset.
 */
/**
 * @param  list<array<string, mixed>>  $redemptionRows
 */
function redemptionTransferFile(string $source, array $redemptionRows): string
{
    return json_encode([
        TransferFormat::KEY_FORMAT => TransferFormat::FORMAT,
        TransferFormat::KEY_MODULE => 'discount',
        TransferFormat::KEY_VERSION => TransferFormat::VERSION,
        TransferFormat::KEY_EXPORTED_AT => '2026-01-15T10:00:00+00:00',
        TransferFormat::KEY_SOURCE_INSTALLATION => $source,
        TransferFormat::KEY_SECTIONS => [
            [TransferFormat::KEY_ENTITY => 'redemptions', TransferFormat::KEY_ROWS => $redemptionRows],
        ],
    ]);
}

it('skips order-linked redemptions with an expected warning, then resolves them on pass 2 (C5/F9)', function () {
    $seeded = $this->seedTransferDiscount();
    $this->actingAs($this->superAdminUser());
    // The seed helper includes its own redemption row — this test starts
    // from a clean history so counts are attributable to the import.
    Redemption::query()->delete();

    $source = (string) Str::uuid();
    $localCouponId = $seeded['coupon']->id;
    // The acting admin's REAL id — PostgreSQL sequences keep values from
    // rolled-back inserts, so id-1 assumptions break full-suite runs.
    $localCustomerId = Auth::id();

    // Prior imports of the restore sequence: User + Order both landed.
    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'user', 'entity' => 'users',
        'source_id' => 500, 'local_id' => $localCustomerId,
    ]);

    $rows = [
        ['source_id' => 300, 'payload' => [
            'coupon_code' => 'WELCOME10', 'order_id' => 400, 'user_id' => 500,
            'discount_amount' => '20.00', 'created_at' => '2026-01-05T10:00:00+00:00',
        ]],
    ];

    // PASS 1: the Order import hasn't happened — expected skip, not fail.
    $pass1 = app(ModuleTransferRunner::class)->run(
        't.json',
        redemptionTransferFile($source, $rows),
        TransferStrategyEnum::UPDATE,
        false,
        'discount',
        app(DiscountTransfer::class),
    );

    expect($pass1['failed_count'])->toBe(0)
        ->and($pass1['skipped_count'])->toBe(1)
        ->and(array_filter($pass1['warnings'], fn (string $w): bool => str_contains($w, 'pending_order_import')) !== [])
        ->and(Redemption::query()->count())->toBe(0);

    // The Order import happened; its ledger is now known.
    $localOrderId = DB::table('order_orders')->insertGetId([
        'tenant_id' => 1,
        'user_id' => $localCustomerId, 'order_code' => 'SRC-2026-0400',
        'status' => 'completed', 'payment_status' => 'paid',
        'total_amount' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'order', 'entity' => 'orders',
        'source_id' => 400, 'local_id' => $localOrderId,
    ]);

    // PASS 2: the same file resolves through the ledger.
    $pass2 = app(ModuleTransferRunner::class)->run(
        't.json',
        redemptionTransferFile($source, $rows),
        TransferStrategyEnum::UPDATE,
        false,
        'discount',
        app(DiscountTransfer::class),
    );

    expect($pass2['errors'])->toBe([])
        ->and($pass2['status'])->toBe('completed')
        ->and($pass2['created_count'])->toBe(1);

    $redemption = Redemption::query()->first();
    expect($redemption)->not->toBeNull()
        ->and($redemption->coupon_id)->toBe($localCouponId)
        ->and((int) $redemption->order_id)->toBe($localOrderId)
        // Historical timestamps preserved; checkout never replayed.
        ->and((float) $redemption->discount_amount)->toBe(20.00);

    // usage_count recomputed from the LOCAL dataset (1 redemption).
    expect(Coupon::query()->find($localCouponId)->usage_count)->toBe(1);

    // Re-import converges: skipped, usage_count stays consistent.
    $pass3 = app(ModuleTransferRunner::class)->run(
        't.json',
        redemptionTransferFile($source, $rows),
        TransferStrategyEnum::UPDATE,
        false,
        'discount',
        app(DiscountTransfer::class),
    );
    expect($pass3['created_count'])->toBe(0)
        ->and(Redemption::query()->count())->toBe(1)
        ->and(Coupon::query()->find($localCouponId)->usage_count)->toBe(1);
});

it('fails redemptions whose coupon does not exist locally (config must restore first)', function () {
    $this->actingAs($this->superAdminUser());

    $summary = app(ModuleTransferRunner::class)->run(
        't.json',
        redemptionTransferFile((string) Str::uuid(), [
            ['source_id' => 300, 'payload' => [
                'coupon_code' => 'NEVERSEEN', 'order_id' => 400, 'user_id' => 500,
                'discount_amount' => '5.00', 'created_at' => '2026-01-05T10:00:00+00:00',
            ]],
        ]),
        TransferStrategyEnum::UPDATE,
        false,
        'discount',
        app(DiscountTransfer::class),
    );

    expect($summary['failed_count'])->toBe(1)
        ->and($summary['errors'][0])->toContain('unknown_coupon:NEVERSEEN');
});
