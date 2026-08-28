<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(VariantSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(VariantSchema::PRODUCT_ID)->constrained(ProductSchema::TABLE)->onDelete('cascade');
            $table->string(VariantSchema::SKU)->unique();
            $table->string(VariantSchema::BARCODE)->unique()->nullable();
            $table->decimal(VariantSchema::PRICE, 12, 2)->unsigned()->default(0.00);
            $table->unsignedSmallInteger(VariantSchema::QUANTITY)->default(0);
            $table->decimal(VariantSchema::COMPARE_PRICE, 12, 2)->unsigned()->nullable();
            $table->decimal(VariantSchema::COST_PRICE, 12, 2)->unsigned()->nullable();
            $table->boolean(VariantSchema::IS_DEFAULT)->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(VariantSchema::TABLE, function (Blueprint $table) {
            $table->dropForeign([VariantSchema::PRODUCT_ID]);
        });
        Schema::dropIfExists(VariantSchema::TABLE);
    }
};
