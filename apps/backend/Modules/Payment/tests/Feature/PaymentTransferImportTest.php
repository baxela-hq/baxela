<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFormat;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Modules\Payment\Tests\Feature\HelperTrait;
use Modules\Payment\Transfer\PaymentTransfer;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Payment import semantics (C5): the ledger imports as INERT records —
 * no gateway code, no state transitions, no order updates — with
 * remap-first identity and exactly-one adoption (F24).
 */
function paymentTransferFile(string $source, int $sourceOrderId, int $sourcePaymentId, array $paymentOverrides = [], array $refundRows = []): string
{
    $now = '2026-01-15T12:00:00+00:00';

    $payment = [
        'id' => $sourcePaymentId,
        'order_id' => $sourceOrderId,
        'transaction_id' => 'src-txn-0001',
        'gateway_transaction_id' => 'gateway-psp-1',
        'method' => 'manual',
        'amount' => '200.00',
        'currency_id' => null,
        'status' => 'success',
        'created_at' => $now,
        'updated_at' => $now,
        ...$paymentOverrides,
    ];

    return json_encode([
        TransferFormat::KEY_FORMAT => TransferFormat::FORMAT,
        TransferFormat::KEY_MODULE => 'payment',
        TransferFormat::KEY_VERSION => TransferFormat::VERSION,
        TransferFormat::KEY_EXPORTED_AT => $now,
        TransferFormat::KEY_SOURCE_INSTALLATION => $source,
        TransferFormat::KEY_SECTIONS => [
            [TransferFormat::KEY_ENTITY => 'payment-methods', TransferFormat::KEY_ROWS => [
                ['source_id' => 1, 'payload' => ['method' => 'manual', 'is_active' => true, 'sort_order' => 5]],
            ]],
            [TransferFormat::KEY_ENTITY => 'payments', TransferFormat::KEY_ROWS => [
                ['source_id' => $sourcePaymentId, 'payload' => $payment],
            ]],
            [TransferFormat::KEY_ENTITY => 'refunds', TransferFormat::KEY_ROWS => $refundRows],
        ],
    ]);
}

it('imports the money trail inertly — records land, nothing else moves (C5/C3)', function () {
    $seeded = $this->seedOrderWithPayment();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localOrder = $seeded['order'];

    // The Order import's ledger: source order 400 → this store's order.
    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'order', 'entity' => 'orders',
        'source_id' => 400, 'local_id' => $localOrder->id,
    ]);

    $orderStatusBefore = $localOrder->refresh()->status->value;
    $orderPaymentStatusBefore = $localOrder->refresh()->payment_status->value;

    $summary = app(ModuleTransferRunner::class)->run(
        't.json',
        paymentTransferFile($source, 400, 600),
        TransferStrategyEnum::UPDATE,
        false,
        'payment',
        app(PaymentTransfer::class),
    );

    expect($summary['status'])->toBe('completed')
        ->and($summary['errors'])->toBe([]);

    // The payment row landed, resolved to the LOCAL order.
    $payment = DB::table('payment_payments')
        ->where(PaymentSchema::ORDER_ID, $localOrder->id)
        ->where(PaymentSchema::TRANSACTION_ID, 'src-txn-0001')
        ->first();
    expect($payment)->not->toBeNull()
        ->and($payment->method)->toBe('manual')
        ->and($payment->status)->toBe('success')
        // Inertness: source timestamps preserved (query-builder write;
        // storage normalizes the ISO string per driver).
        ->and(str_starts_with((string) $payment->created_at, '2026-01-15'))->toBeTrue();

    // The method upserted by its natural key.
    expect(DB::table('payment_payment_methods')->where('method', 'manual')->value('is_active'))->toBe(1);

    // NOTHING else moved: the order's state is untouched by the
    // payment import (no markAsPaid, no events, no transitions).
    expect($localOrder->refresh()->status->value)->toBe($orderStatusBefore)
        ->and($localOrder->refresh()->payment_status->value)->toBe($orderPaymentStatusBefore)
        // No events for the imported payment (no notifications fan-out).
        ->and(DB::table('notification_notifications')->count())->toBe(0);

    // Re-import: the ledger remap is the identity — skip, no duplicate.
    $second = app(ModuleTransferRunner::class)->run(
        't.json',
        paymentTransferFile($source, 400, 600),
        TransferStrategyEnum::UPDATE,
        false,
        'payment',
        app(PaymentTransfer::class),
    );
    expect($second['created_count'])->toBe(0)
        ->and($second['skipped_count'])->toBe(1)
        ->and(DB::table('payment_payments')->where(PaymentSchema::TRANSACTION_ID, 'src-txn-0001')->count())->toBe(1);
});

it('adopts a matching local payment only via the exact transaction tuple (F24)', function () {
    $seeded = $this->seedOrderWithPayment();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localOrder = $seeded['order'];

    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'order', 'entity' => 'orders',
        'source_id' => 400, 'local_id' => $localOrder->id,
    ]);

    // A pre-existing local payment that IS the same payment (an earlier
    // partial import): same order, method, amount, non-null txn id.
    $existingId = DB::table('payment_payments')->insertGetId([
        'order_id' => $localOrder->id, 'transaction_id' => 'src-txn-0001',
        'method' => 'manual', 'amount' => '200.00', 'currency_id' => null,
        'status' => 'success', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $summary = app(ModuleTransferRunner::class)->run(
        't.json',
        paymentTransferFile($source, 400, 600),
        TransferStrategyEnum::UPDATE,
        false,
        'payment',
        app(PaymentTransfer::class),
    );

    // Adopted (skipped), not duplicated — and the ledger now maps it.
    // (created_count is 1: the payment-METHOD section upserted its row.)
    expect($summary['sections']['payments']['created'])->toBe(0)
        ->and($summary['sections']['payments']['skipped'])->toBe(1)
        ->and(DB::table('payment_payments')->where(PaymentSchema::TRANSACTION_ID, 'src-txn-0001')->count())->toBe(1)
        ->and(TransferRemap::query()->where('module', 'payment')->where('entity', 'payments')->where('source_id', 600)->value('local_id'))
        ->toBe($existingId);
});

it('fails ambiguous adoption instead of guessing (F24)', function () {
    $seeded = $this->seedOrderWithPayment();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localOrder = $seeded['order'];

    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'order', 'entity' => 'orders',
        'source_id' => 400, 'local_id' => $localOrder->id,
    ]);

    // TWO local payments match the tuple — adoption is ambiguous.
    foreach ([1, 2] as $i) {
        DB::table('payment_payments')->insert([
            'order_id' => $localOrder->id, 'transaction_id' => 'src-txn-0001',
            'method' => 'manual', 'amount' => '200.00', 'currency_id' => null,
            'status' => 'success', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $summary = app(ModuleTransferRunner::class)->run(
        't.json',
        paymentTransferFile($source, 400, 600),
        TransferStrategyEnum::UPDATE,
        false,
        'payment',
        app(PaymentTransfer::class),
    );

    expect($summary['failed_count'])->toBe(1)
        ->and(collect($summary['errors'])->first())->toContain('ambiguous_payment_match');
});

it('imports refunds keyed by uuid against the remapped payment (C5)', function () {
    $seeded = $this->seedOrderWithPayment();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localOrder = $seeded['order'];

    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'order', 'entity' => 'orders',
        'source_id' => 400, 'local_id' => $localOrder->id,
    ]);

    $refundPayload = [
        'id' => 900, 'uuid' => '0f0e0d0c-0b0a-4918-8276-414243444546',
        'payment_id' => 600, 'order_id' => 400, 'amount' => '50.00',
        'reason' => 'return', 'status' => 'success',
        'transaction_id' => 'gateway-refund-1', 'refunded_by' => 77,
        'created_at' => '2026-01-16T09:00:00+00:00',
        'updated_at' => '2026-01-16T09:00:00+00:00',
    ];

    $summary = app(ModuleTransferRunner::class)->run(
        't.json',
        paymentTransferFile($source, 400, 600, refundRows: [
            ['source_id' => 900, 'payload' => $refundPayload],
        ]),
        TransferStrategyEnum::UPDATE,
        false,
        'payment',
        app(PaymentTransfer::class),
    );

    expect($summary['status'])->toBe('completed');

    $paymentLocalId = TransferRemap::query()->where('module', 'payment')->where('entity', 'payments')->where('source_id', 600)->value('local_id');
    $refund = DB::table('payment_refunds')->where(PaymentRefundSchema::UUID, '0f0e0d0c-0b0a-4918-8276-414243444546')->first();
    expect($refund)->not->toBeNull()
        // The refund settled the LOCAL payment, the LOCAL order.
        ->and((int) $refund->payment_id)->toBe((int) $paymentLocalId)
        ->and((int) $refund->order_id)->toBe($localOrder->id)
        // Unresolvable admin attributor nulls out with a warning.
        ->and($refund->refunded_by)->toBeNull();

    // UUID idempotency: re-import doesn't duplicate.
    $second = app(ModuleTransferRunner::class)->run(
        't.json',
        paymentTransferFile($source, 400, 600, refundRows: [
            ['source_id' => 900, 'payload' => $refundPayload],
        ]),
        TransferStrategyEnum::UPDATE,
        false,
        'payment',
        app(PaymentTransfer::class),
    );
    expect(DB::table('payment_refunds')->where(PaymentRefundSchema::UUID, '0f0e0d0c-0b0a-4918-8276-414243444546')->count())->toBe(1);
});
