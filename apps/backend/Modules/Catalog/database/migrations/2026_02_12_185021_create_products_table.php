<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProductSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->enum(ProductSchema::TYPE, ProductTypeEnum::cases());
            $table->enum(ProductSchema::STATUS, ProductStatusEnum::cases());
            $table->boolean(ProductSchema::IS_PUBLISHED)->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create(ProductTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(ProductTranslationSchema::PRODUCT_ID)
                ->constrained(ProductSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(ProductTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(ProductTranslationSchema::TITLE);
            $table->string(ProductTranslationSchema::SLUG);
            $table->longText(ProductTranslationSchema::CONTENT);
            $table->string(ProductTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([ProductTranslationSchema::PRODUCT_ID, ProductTranslationSchema::LANGUAGE_ID]);
            $table->unique([ProductTranslationSchema::LANGUAGE_ID, ProductTranslationSchema::SLUG]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        Schema::dropIfExists(ProductTranslationSchema::TABLE);
        Schema::dropIfExists(ProductSchema::TABLE);
    }
};
