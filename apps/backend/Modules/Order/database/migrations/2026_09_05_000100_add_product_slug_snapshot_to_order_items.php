<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

return new class extends Migration
{
    /**
     * Add the product slug snapshot to order items for databases created
     * before the column existed (fresh ones get it straight from the create
     * migration), then backfill existing rows from the live products.
     */
    public function up(): void
    {
        if (! Schema::hasColumn(OrderItemSchema::TABLE, OrderItemSchema::PRODUCT_SLUG_SNAPSHOT)) {
            Schema::table(OrderItemSchema::TABLE, function (Blueprint $table): void {
                $table->string(OrderItemSchema::PRODUCT_SLUG_SNAPSHOT)->nullable();
            });
        }

        $defaultLanguageId = DB::table(LanguageSchema::TABLE)
            ->where(LanguageSchema::IS_DEFAULT, true)
            ->value(LanguageSchema::ID);

        DB::table(OrderItemSchema::TABLE)
            ->whereNull(OrderItemSchema::PRODUCT_SLUG_SNAPSHOT)
            ->get(['id', OrderItemSchema::VARIANT_ID])
            ->each(function ($item) use ($defaultLanguageId): void {
                $productId = DB::table(VariantSchema::TABLE)
                    ->where(VariantSchema::ID, $item->{OrderItemSchema::VARIANT_ID})
                    ->value(VariantSchema::PRODUCT_ID);

                $slug = $productId
                    ? DB::table(ProductTranslationSchema::TABLE)
                        ->where(ProductTranslationSchema::PRODUCT_ID, $productId)
                        ->orderByRaw(ProductTranslationSchema::LANGUAGE_ID.' = ? desc', [$defaultLanguageId])
                        ->whereNotNull(ProductTranslationSchema::SLUG)
                        ->value(ProductTranslationSchema::SLUG)
                    : null;

                DB::table(OrderItemSchema::TABLE)
                    ->where('id', $item->id)
                    ->update([OrderItemSchema::PRODUCT_SLUG_SNAPSHOT => $slug]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn(OrderItemSchema::TABLE, OrderItemSchema::PRODUCT_SLUG_SNAPSHOT)) {
            Schema::table(OrderItemSchema::TABLE, function (Blueprint $table): void {
                $table->dropColumn(OrderItemSchema::PRODUCT_SLUG_SNAPSHOT);
            });
        }
    }
};
