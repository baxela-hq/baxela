<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Variant\VariantSchema;
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
        $missing = DB::table(VariantSchema::TABLE)
            ->leftJoin(
                InventoryStockSchema::TABLE,
                InventoryStockSchema::TABLE.'.'.InventoryStockSchema::VARIANT_ID,
                '=',
                VariantSchema::TABLE.'.'.VariantSchema::ID
            )
            ->whereNull(InventoryStockSchema::TABLE.'.'.InventoryStockSchema::ID)
            ->get([
                VariantSchema::TABLE.'.'.VariantSchema::ID.' as variant_id',
                VariantSchema::TABLE.'.'.VariantSchema::QUANTITY.' as quantity',
            ]);

        foreach ($missing->chunk(500) as $chunk) {
            DB::table(InventoryStockSchema::TABLE)->insert(
                $chunk->map(fn ($variant): array => [
                    InventoryStockSchema::VARIANT_ID => $variant->variant_id,
                    InventoryStockSchema::QUANTITY => (int) ($variant->quantity ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }
    }

    public function down(): void
    {
        // Data backfill with no source-of-truth to restore to — nothing to
        // reverse.
    }
};
