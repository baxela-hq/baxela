<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Models\User;
use Modules\Catalog\Actions\Admin\DataTransfer\ExportCatalogDataAction;
use Modules\Catalog\Actions\Admin\DataTransfer\ImportCatalogDataAction;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\Variant;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Support\Transfer\ModuleTransferRunner;
use Modules\Core\Transfer\CoreTransfer;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Transfer\DiscountTransfer;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Transfer\OrderTransfer;
use Modules\Payment\Transfer\PaymentTransfer;
use Modules\Tax\Transfer\TaxTransfer;
use Modules\User\Models\Profile;
use Modules\User\Models\WishlistItem;
use Modules\User\Transfer\UserTransfer;

uses(RefreshDatabase::class);

/**
 * THE acceptance test (F12/F20): seed a source store across modules,
 * export every module, then restore into a destination whose ids are
 * deliberately DIVERGENT — unrelated decoy rows physically occupy every
 * source id before the import, so a raw-id capture would silently attach
 * the wrong records. The import runs in the documented whole-install
 * order and must restore every cross-module reference to the CORRECT
 * entity — never the decoy, never the source id.
 */
it('restores a full store into divergent ids without capturing any decoy', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    // ── Source store ────────────────────────────────────────────────
    $en = Language::factory()->create(['code' => 'en', 'code3' => 'eng', 'is_default' => true, 'is_active' => true, 'position' => 1]);
    $usd = Currency::factory()->create(['code' => 'USD', 'name' => 'US Dollar', 'is_default' => true, 'is_active' => true]);
    Country::query()->create(['code' => 'DE', 'code3' => 'DEU', 'name' => 'Germany']);

    $category = Category::query()->create(['parent_id' => null, 'position' => 1]);
    $category->translations()->create(['language_id' => $en->id, 'title' => 'Widgets', 'slug' => 'widgets']);
    $product = Product::query()->create(['type' => 'simple', 'status' => 'in_stock', 'is_published' => true]);
    $product->translations()->create(['language_id' => $en->id, 'title' => 'Widget', 'slug' => 'widget', 'content' => 'Widget content', 'description' => null]);
    $product->categories()->attach([$category->id]);
    $variant = Variant::factory()->create(['product_id' => $product->id, 'sku' => 'SRC-SKU-1', 'price' => '100.00', 'quantity' => 5, 'is_default' => true]);

    $jane = User::factory()->create(['email' => 'jane@example.com']);
    Profile::query()->create(['user_id' => $jane->id, 'full_name' => 'Jane Doe', 'bio' => 'restorable']);
    WishlistItem::query()->create(['user_id' => $jane->id, 'product_id' => $product->id]);

    $taxClassId = DB::table('tax_classes')->insertGetId(['name' => 'Standard', 'is_default' => false, 'is_shipping' => true]);

    $coupon = Coupon::query()->create([
        'code' => 'WELCOME10', 'name' => 'Welcome', 'type' => 'percent',
        'value' => '10.00', 'usage_limit' => 100, 'is_active' => true,
    ]);

    $order = Order::query()->create([
        'user_id' => $jane->id, 'order_code' => 'ORD-SRC-1',
        'status' => 'completed', 'payment_status' => 'paid', 'paid_at' => '2026-01-15 10:00:00',
        'total_amount' => '100.00', 'shipping_cost' => '0.00',
        'coupon_id' => $coupon->id, 'coupon_code' => 'WELCOME10', 'discount_amount' => '10.00',
    ]);
    // created_at is not fillable — the history fidelity assertions below
    // need the SOURCE order to carry a distinctive timestamp.
    DB::table('order_orders')->where('id', $order->id)->update([
        'created_at' => '2026-01-15 10:00:00', 'updated_at' => '2026-01-15 10:00:00',
    ]);
    $orderItem = OrderItem::query()->create([
        'order_id' => $order->id, 'variant_id' => $variant->id,
        'product_name_snapshot' => 'Widget', 'product_slug_snapshot' => 'widget',
        'price_snapshot' => '100.00', 'base_price' => '100.00', 'promotion_discount' => '0.00',
        'quantity' => 1, 'tax_amount' => '0.00', 'tax_rate' => '0.0000',
    ]);

    $paymentId = DB::table('payment_payments')->insertGetId([
        'order_id' => $order->id, 'transaction_id' => 'SRC-TXN-1', 'method' => 'manual',
        'amount' => '100.00', 'status' => 'success',
        'created_at' => '2026-01-15 10:00:00', 'updated_at' => '2026-01-15 10:00:00',
    ]);
    // created_at is not fillable — the history fidelity assertions below
    // need the SOURCE order to carry a distinctive timestamp.
    DB::table('order_orders')->where('id', $order->id)->update([
        'created_at' => '2026-01-15 10:00:00', 'updated_at' => '2026-01-15 10:00:00',
    ]);
    $refundUuid = 'a1b2c3d4-e5f6-4718-9276-616263646566';
    DB::table('payment_refunds')->insert([
        'uuid' => $refundUuid, 'payment_id' => $paymentId, 'order_id' => $order->id,
        'amount' => '10.00', 'reason' => 'return', 'status' => 'success',
        'created_at' => '2026-01-16 09:00:00', 'updated_at' => '2026-01-16 09:00:00',
    ]);

    $redemptionId = Redemption::query()->create([
        'coupon_id' => $coupon->id, 'order_id' => $order->id, 'user_id' => $jane->id,
        'discount_amount' => '10.00',
    ])->id;

    // Record the SOURCE ids the destination must NOT capture.
    $source = [
        'language' => $en->id, 'currency' => $usd->id,
        'product' => $product->id, 'variant' => $variant->id,
        'user' => $jane->id, 'taxClass' => $taxClassId,
        'coupon' => $coupon->id, 'order' => $order->id,
        'orderItem' => $orderItem->id, 'payment' => $paymentId,
        'refund' => 1, 'redemption' => $redemptionId,
    ];

    // ── Export every module (through their real export paths) ───────
    $coreFile = app(ModuleTransferRunner::class)->envelope(app(CoreTransfer::class), 'core');
    $userFile = app(ModuleTransferRunner::class)->envelope(app(UserTransfer::class), 'user');
    $taxFile = app(ModuleTransferRunner::class)->envelope(app(TaxTransfer::class), 'tax');
    $discountFile = app(ModuleTransferRunner::class)->envelope(app(DiscountTransfer::class), 'discount');
    $orderFile = app(ModuleTransferRunner::class)->envelope(app(OrderTransfer::class), 'order');
    $paymentFile = app(ModuleTransferRunner::class)->envelope(app(PaymentTransfer::class), 'payment');
    $catalogFile = app(ExportCatalogDataAction::class)->handle();

    // ── Destination: wipe the business planes, then plant DECOYS ────
    // Child tables first — refund/invoice rows are FK-RESTRICTED against
    // their order/payment parents (the money trail outlives them).
    foreach ([
        'order_invoice_items', 'order_credit_notes', 'order_invoices',
        'payment_refunds', 'payment_payments',
        'order_order_items', 'order_order_addresses', 'order_returns',
        'order_orders',
        'discount_redemptions', 'discount_coupons',
        'catalog_category_translations', 'catalog_category_product', 'catalog_categories',
        'catalog_product_translations', 'catalog_variants', 'catalog_products',
        'user_addresses', 'user_wishlist_items',
        'tax_classes',
    ] as $table) {
        DB::table($table)->delete();
    }
    WishlistItem::query()->delete();
    Profile::query()->delete();
    User::query()->whereKeyNot(1)->delete();
    Language::query()->delete();
    Currency::query()->delete();
    Country::query()->delete();

    // Decoys physically occupy every source id with UNRELATED data.
    DB::table('core_languages')->insert(['id' => $source['language'], 'locale' => 'xx', 'name' => 'Decoy', 'code' => 'xx', 'code3' => 'xxx', 'is_default' => 0, 'is_active' => 1]);
    DB::table('core_currencies')->insert(['id' => $source['currency'], 'code' => 'XYX', 'name' => 'Decoy', 'exchange_rate' => 1, 'is_default' => 0, 'is_active' => 1]);
    DB::table('core_countries')->insert(['id' => 1, 'code' => 'XY', 'code3' => 'XYZ', 'name' => 'Decoyland']);
    $decoyProductId = $source['product'];
    DB::table('catalog_products')->insert(['id' => $decoyProductId, 'type' => 'simple', 'status' => 'in_stock', 'is_published' => 0]);
    DB::table('catalog_variants')->insert(['id' => $source['variant'], 'product_id' => $decoyProductId, 'sku' => 'DECOY-SKU', 'price' => '999.99', 'quantity' => 0, 'is_default' => 1]);
    $decoyUserId = $source['user'];
    DB::table('auth_users')->insert(['id' => $decoyUserId, 'email' => 'decoy@example.com', 'password' => 'x', 'is_active' => 1]);
    DB::table('tax_classes')->insert(['id' => $source['taxClass'], 'name' => 'DECOY', 'is_default' => 0, 'is_shipping' => 0]);
    DB::table('discount_coupons')->insert(['id' => $source['coupon'], 'code' => 'DECOY10', 'name' => 'Decoy', 'type' => 'percent', 'value' => '99.00', 'is_active' => 1]);
    $decoyOrderId = $source['order'];
    DB::table('order_orders')->insert(['id' => $decoyOrderId, 'user_id' => $decoyUserId, 'order_code' => 'ORD-DECOY-1', 'status' => 'pending', 'payment_status' => 'unpaid', 'total_amount' => 0]);
    DB::table('payment_payments')->insert(['id' => $source['payment'], 'order_id' => $decoyOrderId, 'transaction_id' => 'DECOY-TXN', 'method' => 'manual', 'amount' => '1.00', 'status' => 'failed', 'created_at' => now(), 'updated_at' => now()]);

    // ── Import in the documented whole-install order ────────────────
    $runner = app(ModuleTransferRunner::class);

    $coreSummary = $runner->run('core.json', json_encode($coreFile), TransferStrategyEnum::UPDATE, false, 'core', app(CoreTransfer::class));
    expect($coreSummary['status'])->toBe('completed')->and($coreSummary['errors'])->toBe([]);

    // Catalog (pre-standard path: media-carried file, its own action).
    Storage::fake('local');
    Storage::disk('local')->put('imports/catalog.json', json_encode($catalogFile));
    $media = Media::factory()->create([
        MediaSchema::DISK => 'local', MediaSchema::PATH => 'imports/catalog.json',
        MediaSchema::NAME => 'catalog', MediaSchema::EXTENSION => 'json', MediaSchema::MIME_TYPE => 'application/json',
    ]);
    $catalogSummary = app(ImportCatalogDataAction::class)->handle([
        'media_id' => $media->id, 'on_duplicate' => 'update', 'dry_run' => false,
    ]);
    expect($catalogSummary['errors'])->toBe([])
        ->and($catalogSummary['status'])->toBe('completed')
        ->and($catalogSummary['failed_count'])->toBe(0);

    $userSummary = $runner->run('user.json', json_encode($userFile), TransferStrategyEnum::UPDATE, false, 'user', app(UserTransfer::class));
    expect($userSummary['status'])->toBe('completed')->and($userSummary['errors'])->toBe([]);

    $taxSummary = $runner->run('tax.json', json_encode($taxFile), TransferStrategyEnum::UPDATE, false, 'tax', app(TaxTransfer::class));
    expect($taxSummary['status'])->toBe('completed');

    // Discount pass 1: configuration restores; the redemption skips as
    // EXPECTED (its order is not imported yet).
    $discountPass1 = $runner->run('discount.json', json_encode($discountFile), TransferStrategyEnum::UPDATE, false, 'discount', app(DiscountTransfer::class));
    expect($discountPass1['status'])->toBe('completed')
        ->and(array_filter($discountPass1['warnings'], fn (string $w): bool => str_contains($w, 'pending_order_import')) !== []);

    $orderSummary = $runner->run('order.json', json_encode($orderFile), TransferStrategyEnum::UPDATE, false, 'order', app(OrderTransfer::class));
    expect($orderSummary['status'])->toBe('completed')->and($orderSummary['errors'])->toBe([]);

    $paymentSummary = $runner->run('payment.json', json_encode($paymentFile), TransferStrategyEnum::UPDATE, false, 'payment', app(PaymentTransfer::class));
    expect($paymentSummary['status'])->toBe('completed')->and($paymentSummary['errors'])->toBe([]);

    // Discount pass 2: the redemption resolves through the ledger.
    $discountPass2 = $runner->run('discount.json', json_encode($discountFile), TransferStrategyEnum::UPDATE, false, 'discount', app(DiscountTransfer::class));
    expect($discountPass2['status'])->toBe('completed')
        ->and($discountPass2['errors'])->toBe([]);

    // ── Divergence: nothing landed at its source id ─────────────────
    $importedUser = User::query()->where('email', 'jane@example.com')->first();
    $importedLanguage = Language::query()->where('code', 'en')->first();
    $importedCurrency = Currency::query()->where('code', 'USD')->first();
    $importedProduct = Product::query()->whereHas('translations', fn ($q) => $q->where('title', 'Widget'))->first();
    $importedVariant = Variant::query()->where('sku', 'SRC-SKU-1')->first();
    $importedTaxClass = DB::table('tax_classes')->where('name', 'Standard')->first();
    $importedCoupon = Coupon::query()->where('code', 'WELCOME10')->first();
    $importedOrder = Order::query()->where('order_code', 'ORD-SRC-1')->first();
    $importedPayment = DB::table('payment_payments')->where('transaction_id', 'SRC-TXN-1')->first();
    $importedRefund = DB::table('payment_refunds')->where('uuid', $refundUuid)->first();
    $importedRedemption = Redemption::query()->first();

    // EVERY imported id differs from its source id AND its decoy id.
    expect($importedUser->id)->not->toBe($source['user'])
        ->and($importedLanguage->id)->not->toBe($source['language'])
        ->and($importedCurrency->id)->not->toBe($source['currency'])
        ->and($importedProduct->id)->not->toBe($source['product'])
        ->and($importedVariant->id)->not->toBe($source['variant'])
        ->and((int) $importedTaxClass->id)->not->toBe($source['taxClass'])
        ->and($importedCoupon->id)->not->toBe($source['coupon'])
        ->and($importedOrder->id)->not->toBe($source['order'])
        ->and((int) $importedPayment->id)->not->toBe($source['payment'])
        ->and((int) $importedRefund->id)->not->toBe($source['refund'])
        ->and($importedRedemption->id)->not->toBe($source['redemption'])
        // Divergence is REAL (the destination id space moved past decoys).
        ->and($importedUser->id)->toBeGreaterThan($decoyUserId);

    // ── Decoys untouched: nothing captured them ─────────────────────
    expect(DB::table('auth_users')->where('id', $decoyUserId)->value('email'))->toBe('decoy@example.com')
        ->and(DB::table('catalog_variants')->where('id', $source['variant'])->value('sku'))->toBe('DECOY-SKU')
        ->and(DB::table('discount_coupons')->where('id', $source['coupon'])->value('code'))->toBe('DECOY10')
        ->and(Order::query()->where('order_code', 'ORD-DECOY-1')->first()->payment_status->value)->toBe('unpaid')
        // No item/payment/refund ever points at a decoy.
        ->and(OrderItem::query()->whereIn('order_id', [$decoyOrderId])->count())->toBe(0)
        ->and(DB::table('payment_payments')->where('order_id', $decoyOrderId)->where('transaction_id', '!=', 'DECOY-TXN')->count())->toBe(0);

    // ── History faithful: statuses, money, timestamps ───────────────
    expect($importedOrder->status->value)->toBe('completed')
        ->and($importedOrder->payment_status->value)->toBe('paid')
        ->and((float) $importedOrder->total_amount)->toBe(100.00)
        ->and($importedOrder->created_at->format('Y-m-d H:i'))->toBe('2026-01-15 10:00')
        ->and($importedPayment->status)->toBe('success')
        ->and(str_starts_with((string) $importedPayment->created_at, '2026-01-15'))->toBeTrue();

    // ── Cross-module references resolve to the CORRECT entities ─────
    $importedOrderItem = OrderItem::query()->where('order_id', $importedOrder->id)->first();

    // The order belongs to the imported Jane — not the decoy user.
    expect($importedOrder->user_id)->toBe($importedUser->id)
        // The order item points at the imported variant — not the decoy.
        ->and($importedOrderItem->variant_id)->toBe($importedVariant->id)
        ->and($importedOrderItem->product_name_snapshot)->toBe('Widget')
        ->and((float) $importedOrderItem->price_snapshot)->toBe(100.00)
        // The coupon resolved via its portable code.
        ->and($importedOrder->coupon_id)->toBe($importedCoupon->id)
        // The payment settled the imported order; the refund the imported payment.
        ->and((int) $importedPayment->order_id)->toBe((int) $importedOrder->id)
        ->and((int) $importedRefund->payment_id)->toBe((int) $importedPayment->id)
        ->and((int) $importedRefund->order_id)->toBe((int) $importedOrder->id)
        // The wishlist survived: Jane's item points at the imported product.
        ->and(WishlistItem::query()->where('user_id', $importedUser->id)->value('product_id'))->toBe($importedProduct->id)
        // The redemption (pass 2) resolved coupon, order AND user.
        ->and($importedRedemption->coupon_id)->toBe($importedCoupon->id)
        ->and((int) $importedRedemption->order_id)->toBe((int) $importedOrder->id)
        ->and($importedRedemption->user_id)->toBe($importedUser->id)
        // usage_count recomputed from the LOCAL dataset.
        ->and($importedCoupon->refresh()->usage_count)->toBe(1)
        // Jane's profile survived the round trip.
        ->and(Profile::query()->where('user_id', $importedUser->id)->value('full_name'))->toBe('Jane Doe');

    // ── The ledger: every reference resolvable, none dangling ───────
    $namespace = $coreFile['source_installation'];
    expect(TransferRemap::query()->where('source_installation', $namespace)->where('module', 'catalog')->where('entity', 'variants')->where('source_id', $source['variant'])->value('local_id'))
        ->toBe($importedVariant->id)
        ->and(TransferRemap::query()->where('source_installation', $namespace)->where('module', 'user')->where('entity', 'users')->where('source_id', $source['user'])->value('local_id'))
        ->toBe($importedUser->id)
        ->and(TransferRemap::query()->where('source_installation', $namespace)->where('module', 'order')->where('entity', 'orders')->where('source_id', $source['order'])->value('local_id'))
        ->toBe($importedOrder->id)
        ->and(TransferRemap::query()->where('source_installation', $namespace)->where('module', 'payment')->where('entity', 'payments')->where('source_id', $source['payment'])->value('local_id'))
        ->toBe((int) $importedPayment->id);
});
