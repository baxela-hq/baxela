<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\DimensionUnitEnum;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductShippingSchema;
use Modules\Catalog\Schemas\Product\WeightUnitEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProductShippingSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(ProductShippingSchema::PRODUCT_ID)
                ->constrained(ProductSchema::TABLE)->onDelete('cascade');
            $table->decimal(ProductShippingSchema::WEIGHT, 12, 2)->unsigned()->nullable();
            $table->enum(ProductShippingSchema::WEIGHT_UNIT, WeightUnitEnum::cases())->nullable();
            $table->decimal(ProductShippingSchema::PACKAGE_LENGTH, 12, 2)->unsigned()->nullable();
            $table->decimal(ProductShippingSchema::PACKAGE_WIDTH, 12, 2)->unsigned()->nullable();
            $table->decimal(ProductShippingSchema::PACKAGE_HEIGHT, 12, 2)->unsigned()->nullable();
            $table->enum(ProductShippingSchema::DIMENSION_UNIT, DimensionUnitEnum::cases())->nullable();
            $table->boolean(ProductShippingSchema::REQUIRES_SHIPPING)->default(true);
            $table->timestamps();

            $table->unique(ProductShippingSchema::PRODUCT_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ProductShippingSchema::TABLE);
    }
};
