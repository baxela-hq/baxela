<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Models\Variant;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Support\Transfer\TransferFormat;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Tests\Feature\HelperTrait;
use Modules\Order\Transfer\OrderTransfer;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

/**
 * Order import semantics (C5): immutable-history hydration through the
 * query builder — no events, no checkout path — with per-(order ×
 * section) atomicity, remap-driven user/variant resolution and coupon
 * natural-key resolution.
 */

/** Build an order transfer file for one order (source ids in parens). */
/**
 * @param  array<string, mixed>  $overrides
 */
function orderTransferFile(string $source, int $sourceOrderId, int $sourceUserId, int $sourceVariantId, string $orderCode, array $overrides = []): string
{
    $now = '2026-01-15T10:00:00+00:00';

    $order = [
        'id' => $sourceOrderId,
        'user_id' => $sourceUserId,
        'order_code' => $orderCode,
        'status' => 'completed',
        'payment_status' => 'paid',
        'paid_at' => $now,
        'total_amount' => '222.43',
        'currency_id' => null,
        'display_currency_code' => 'EUR',
        'display_fx_rate' => '1.0000',
        'shipping_method_id' => null,
        'shipping_method_name' => 'Standard',
        'shipping_cost' => '5.00',
        'coupon_id' => null,
        'coupon_code' => null,
        'discount_amount' => '0.00',
        'tax_amount' => '0.00',
        'prices_contain_tax' => 1,
        'buyer_vat_id' => null,
        'shipping_tax_amount' => '0.00',
        'shipping_tax_rate' => '0.0000',
        'shipping_tax_class_id' => null,
        'refunded_amount' => '0.00',
        'expires_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
        ...$overrides,
    ];

    return json_encode([
        TransferFormat::KEY_FORMAT => TransferFormat::FORMAT,
        TransferFormat::KEY_MODULE => 'order',
        TransferFormat::KEY_VERSION => TransferFormat::VERSION,
        TransferFormat::KEY_EXPORTED_AT => $now,
        TransferFormat::KEY_SOURCE_INSTALLATION => $source,
        TransferFormat::KEY_SECTIONS => [
            [TransferFormat::KEY_ENTITY => 'orders', TransferFormat::KEY_ROWS => [
                ['source_id' => $sourceOrderId, 'payload' => $order],
            ]],
        ],
    ]);
}

it('hydrates order history with remapped user and preserved code and timestamps (C5)', function () {
    $seeded = $this->seedOrderTransferData();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $sourceOrderId = 500;
    $sourceUserId = 9000;
    $sourceVariantId = 7000;
    $localVariant = $seeded['variant'];

    // The User directory import (a previous real run) recorded the
    // identity of source user 9000: THIS store's customer.
    TransferRemap::query()->create([
        'source_installation' => $source,
        'module' => 'user',
        'entity' => 'users',
        'source_id' => $sourceUserId,
        'local_id' => $seeded['customer']->id,
    ]);
    // ... and the catalog import recorded source variant 7000 → local.
    TransferRemap::query()->create([
        'source_installation' => $source,
        'module' => 'catalog',
        'entity' => 'variants',
        'source_id' => $sourceVariantId,
        'local_id' => $localVariant->id,
    ]);

    $summary = app(ModuleTransferRunner::class)->run(
        'transfer-order.json',
        orderTransferFile($source, $sourceOrderId, $sourceUserId, $sourceVariantId, 'SRC-2026-0001'),
        TransferStrategyEnum::UPDATE,
        false,
        'order',
        app(OrderTransfer::class),
    );

    expect($summary['status'])->toBe('completed')
        ->and($summary['errors'])->toBe([]);

    $order = Order::query()->where(OrderSchema::ORDER_CODE, 'SRC-2026-0001')->first();
    expect($order)->not->toBeNull()
        // History hydrates exactly as exported.
        ->and($order->{OrderSchema::USER_ID})->toBe($seeded['customer']->id)
        ->and($order->status->value)->toBe('completed')
        ->and((float) $order->{OrderSchema::TOTAL_AMOUNT})->toBe(222.43)
        // The timestamps are the SOURCE's, not the import moment (C5).
        ->and($order->created_at->toIso8601String())->toBe('2026-01-15T10:00:00+00:00');

    // The order itself landed in the ledger for Payment/Redemptions.
    expect(TransferRemap::query()->where('module', 'order')->where('entity', 'orders')->where('source_id', $sourceOrderId)->value('local_id'))
        ->toBe((int) $order->id);

    // Re-import converges: no duplicate order.
    app(ModuleTransferRunner::class)->run(
        'transfer-order.json',
        orderTransferFile($source, $sourceOrderId, $sourceUserId, $sourceVariantId, 'SRC-2026-0001'),
        TransferStrategyEnum::UPDATE,
        false,
        'order',
        app(OrderTransfer::class),
    );
    expect(Order::query()->where(OrderSchema::ORDER_CODE, 'SRC-2026-0001')->count())->toBe(1);
});

it('replaces children per order-section atomically, empty means empty (F27)', function () {
    $seeded = $this->seedOrderTransferData();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localVariant = $seeded['variant'];
    $customer = $seeded['customer'];

    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'user', 'entity' => 'users',
        'source_id' => 9000, 'local_id' => $customer->id,
    ]);
    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'catalog', 'entity' => 'variants',
        'source_id' => 7000, 'local_id' => $localVariant->id,
    ]);

    $payload = json_decode(orderTransferFile($source, 500, 9000, 7000, 'SRC-2026-0002'), true);
    $payload[TransferFormat::KEY_SECTIONS][] = [TransferFormat::KEY_ENTITY => 'order-items', TransferFormat::KEY_ROWS => [
        ['source_id' => 8000, 'payload' => [
            'id' => 8000, 'order_id' => 500, 'variant_id' => 7000,
            'product_name_snapshot' => 'Widget', 'product_slug_snapshot' => 'widget',
            'price_snapshot' => '100.00', 'base_price' => '100.00',
            'promotion_discount' => '0.00', 'promotion_id' => null,
            'quantity' => 2, 'tax_amount' => '0.00', 'tax_rate' => '0.0000',
            'tax_class_id' => null, 'created_at' => '2026-01-15T10:00:00+00:00',
        ]],
    ]];

    $runner = app(ModuleTransferRunner::class);
    $runner->run('t.json', json_encode($payload), TransferStrategyEnum::UPDATE, false, 'order', app(OrderTransfer::class));

    $order = Order::query()->where(OrderSchema::ORDER_CODE, 'SRC-2026-0002')->first();
    expect(OrderItem::query()->where(OrderItemSchema::ORDER_ID, $order->id)->count())->toBe(1)
        // The variant resolved through the remap to the LOCAL variant.
        ->and(OrderItem::query()->where(OrderItemSchema::ORDER_ID, $order->id)->value('variant_id'))->toBe($localVariant->id);

    // SECOND import, same order, EMPTY order-items section (F27: an
    // empty section is authoritative — the stale child is removed).
    $empty = $payload;
    $empty[TransferFormat::KEY_SECTIONS][1][TransferFormat::KEY_ROWS] = [];
    $runner->run('t.json', json_encode($empty), TransferStrategyEnum::UPDATE, false, 'order', app(OrderTransfer::class));

    expect(OrderItem::query()->where(OrderItemSchema::ORDER_ID, $order->id)->count())->toBe(0)
        // F23: the deleted child's remap died with it.
        ->and(TransferRemap::query()->where('module', 'order')->where('entity', 'order-items')->count())->toBe(0);

    // ABSENT section is a NO-OP: re-import with orders only, seed a child
    // by hand, re-import → the hand-made child survives.
    DB::table('order_order_items')->insert([
        'tenant_id' => 1,
        'order_id' => $order->id, 'variant_id' => $localVariant->id,
        'product_name_snapshot' => 'Manual', 'product_slug_snapshot' => 'manual',
        'price_snapshot' => '1.00', 'base_price' => null, 'promotion_discount' => null,
        'quantity' => 1, 'tax_amount' => '0.00', 'tax_rate' => '0.0000',
        'created_at' => now(),
    ]);
    $ordersOnly = $payload;
    array_splice($ordersOnly[TransferFormat::KEY_SECTIONS], 1, 1);
    $runner->run('t.json', json_encode($ordersOnly), TransferStrategyEnum::UPDATE, false, 'order', app(OrderTransfer::class));

    expect(OrderItem::query()->where(OrderItemSchema::ORDER_ID, $order->id)->count())->toBe(1);
});

it('fails orders whose user cannot resolve, and freezes their children (C5)', function () {
    $seeded = $this->seedOrderTransferData();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localVariant = $seeded['variant'];

    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'catalog', 'entity' => 'variants',
        'source_id' => 7000, 'local_id' => $localVariant->id,
    ]);

    $payload = json_decode(orderTransferFile($source, 500, 9000, 7000, 'SRC-2026-0003'), true);
    $payload[TransferFormat::KEY_SECTIONS][] = [TransferFormat::KEY_ENTITY => 'order-items', TransferFormat::KEY_ROWS => [
        ['source_id' => 8000, 'payload' => [
            'id' => 8000, 'order_id' => 500, 'variant_id' => 7000,
            'product_name_snapshot' => 'Widget', 'product_slug_snapshot' => 'widget',
            'price_snapshot' => '100.00', 'base_price' => '100.00',
            'promotion_discount' => '0.00', 'promotion_id' => null,
            'quantity' => 2, 'tax_amount' => '0.00', 'tax_rate' => '0.0000',
            'tax_class_id' => null, 'created_at' => '2026-01-15T10:00:00+00:00',
        ]],
    ]];

    $summary = app(ModuleTransferRunner::class)->run(
        't.json', json_encode($payload), TransferStrategyEnum::UPDATE, false, 'order', app(OrderTransfer::class),
    );

    expect($summary['failed_count'])->toBe(2)
        ->and(Order::query()->where(OrderSchema::ORDER_CODE, 'SRC-2026-0003')->count())->toBe(0)
        // The child froze with its parent: no history in the air (C5).
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(array_filter($summary['warnings'], fn (string $w): bool => str_contains($w, 'parent_failed')) !== []);
});

it('skips the whole subtree under SKIP strategy (C5)', function () {
    $seeded = $this->seedOrderTransferData();
    $this->actingAs($this->superAdminUser());

    $source = (string) Str::uuid();
    $localVariant = $seeded['variant'];

    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'user', 'entity' => 'users',
        'source_id' => 9000, 'local_id' => $seeded['customer']->id,
    ]);
    TransferRemap::query()->create([
        'source_installation' => $source, 'module' => 'catalog', 'entity' => 'variants',
        'source_id' => 7000, 'local_id' => $localVariant->id,
    ]);

    // Pre-existing order with the same code (a previous import's).
    Order::query()->create([
        'user_id' => $seeded['customer']->id, 'order_code' => 'SRC-2026-0004',
        'status' => 'completed', 'payment_status' => 'paid',
    ]);
    OrderItem::query()->create([
        'order_id' => Order::query()->where(OrderSchema::ORDER_CODE, 'SRC-2026-0004')->value('id'),
        'variant_id' => $localVariant->id, 'product_name_snapshot' => 'old',
        'product_slug_snapshot' => 'old', 'price_snapshot' => '9.99',
        'base_price' => '9.99', 'promotion_discount' => '0.00', 'quantity' => 1,
    ]);

    $payload = json_decode(orderTransferFile($source, 500, 9000, 7000, 'SRC-2026-0004'), true);
    $payload[TransferFormat::KEY_SECTIONS][] = [TransferFormat::KEY_ENTITY => 'order-items', TransferFormat::KEY_ROWS => [
        ['source_id' => 8000, 'payload' => [
            'id' => 8000, 'order_id' => 500, 'variant_id' => 7000,
            'product_name_snapshot' => 'new', 'product_slug_snapshot' => 'new',
            'price_snapshot' => '100.00', 'base_price' => '100.00',
            'promotion_discount' => '0.00', 'promotion_id' => null,
            'quantity' => 2, 'tax_amount' => '0.00', 'tax_rate' => '0.0000',
            'tax_class_id' => null, 'created_at' => '2026-01-15T10:00:00+00:00',
        ]],
    ]];

    $summary = app(ModuleTransferRunner::class)->run(
        't.json', json_encode($payload), TransferStrategyEnum::SKIP, false, 'order', app(OrderTransfer::class),
    );

    // Parent skipped; the child row skipped with it — untouched history.
    expect($summary['skipped_count'])->toBe(2)
        ->and(OrderItem::query()->where('product_name_snapshot', 'old')->count())->toBe(1)
        ->and(OrderItem::query()->where('product_name_snapshot', 'new')->count())->toBe(0);
});
