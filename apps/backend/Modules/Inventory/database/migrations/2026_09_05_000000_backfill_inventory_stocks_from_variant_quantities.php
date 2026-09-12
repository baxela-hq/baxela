<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

return new class extends Migration
{
    /**
     * One-time backfill: give every variant an inventory ledger row, taking
     * the quantity already maintained on the catalog variant. The sell flow
     * (add-to-cart, checkout) only reads the ledger — variants without a
     * row are not sellable — while those catalog quantities were never
     * seeded into it. Variants whose catalog quantity is null stock 0.
     */
    public function up(): void
    {
        $quantities = app(CatalogGatewayInterface::class)->variantQuantities();

        $existing = DB::table(InventoryStockSchema::TABLE)
            ->whereIn(InventoryStockSchema::VARIANT_ID, $quantities->keys()->all())
            ->pluck(InventoryStockSchema::VARIANT_ID)
            ->all();

        $missing = $quantities->except($existing);

        foreach ($missing->chunk(500) as $chunk) {
            DB::table(InventoryStockSchema::TABLE)->insert(
                $chunk->map(fn ($quantity, $variantId): array => [
                    InventoryStockSchema::VARIANT_ID => $variantId,
                    InventoryStockSchema::QUANTITY => (int) ($quantity ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all()
            );
        }
    }

    public function down(): void
    {
        // Data backfill with no source-of-truth to restore to — nothing to
        // reverse.
    }
};
