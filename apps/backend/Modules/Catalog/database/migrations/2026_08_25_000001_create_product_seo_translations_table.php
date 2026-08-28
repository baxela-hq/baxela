<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProductSeoTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(ProductSeoTranslationSchema::PRODUCT_ID)
                ->constrained(ProductSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(ProductSeoTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(ProductSeoTranslationSchema::META_TITLE)->nullable();
            $table->string(ProductSeoTranslationSchema::META_DESCRIPTION)->nullable();
            $table->string(ProductSeoTranslationSchema::OPEN_GRAPH_TITLE)->nullable();
            $table->string(ProductSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([ProductSeoTranslationSchema::PRODUCT_ID, ProductSeoTranslationSchema::LANGUAGE_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ProductSeoTranslationSchema::TABLE);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
