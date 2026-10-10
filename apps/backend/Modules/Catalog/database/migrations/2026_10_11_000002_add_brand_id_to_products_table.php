<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(ProductSchema::TABLE, function (Blueprint $table) {
            // Deleting a brand unassigns it from its products; the products
            // themselves always survive.
            $table->foreignId(ProductSchema::BRAND_ID)
                ->nullable()
                ->constrained(BrandSchema::TABLE)
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(ProductSchema::TABLE, function (Blueprint $table) {
            $table->dropConstrainedForeignId(ProductSchema::BRAND_ID);
        });
    }
};
