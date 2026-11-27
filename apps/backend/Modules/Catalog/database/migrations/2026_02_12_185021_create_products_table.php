<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProductSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent keys for the layer-3 tenant-safe child FKs.
            $table->unique([TenantColumn::TENANT_ID, ProductSchema::ID], 'catalog_products_tenant_id_unique');
            $table->enum(ProductSchema::TYPE, ProductTypeEnum::cases());
            $table->enum(ProductSchema::STATUS, ProductStatusEnum::cases());
            $table->boolean(ProductSchema::IS_PUBLISHED)->default(false);
            // Loose reference to the Tax module's classes (no cross-module
            // FK — resolution goes through the Tax gateway). Null resolves
            // to the default class at calculation time.
            $table->unsignedBigInteger(ProductSchema::TAX_CLASS_ID)->nullable()->index();
            // Denormalized review aggregate, recalculated by RecalculatesProductRating
            $table->decimal(ProductSchema::RATING_AVG, 3, 2)->nullable();
            $table->unsignedInteger(ProductSchema::RATING_COUNT)->default(0);
            // Deleting a brand unassigns it from its products; the products
            // themselves always survive.
            $table->foreignId(ProductSchema::BRAND_ID)
                ->nullable()
                ->constrained(BrandSchema::TABLE)
                ->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create(ProductTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(ProductTranslationSchema::PRODUCT_ID);
            $table->foreign([TenantColumn::TENANT_ID, ProductTranslationSchema::PRODUCT_ID], 'catalog_product_translations_tenant_product_fk')
                ->references([TenantColumn::TENANT_ID, ProductSchema::ID])
                ->on(ProductSchema::TABLE)->cascadeOnDelete();
            $table->unsignedBigInteger(ProductTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(ProductTranslationSchema::TITLE);
            $table->string(ProductTranslationSchema::SLUG);
            $table->longText(ProductTranslationSchema::CONTENT);
            $table->string(ProductTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([ProductTranslationSchema::PRODUCT_ID, ProductTranslationSchema::LANGUAGE_ID]);
            $table->unique([TenantColumn::TENANT_ID, ProductTranslationSchema::LANGUAGE_ID, ProductTranslationSchema::SLUG]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // MySQL-only statement — migrate:fresh on SQLite (the S.4
        // concurrency suite) would choke on it.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        }
        Schema::dropIfExists(ProductTranslationSchema::TABLE);
        Schema::dropIfExists(ProductSchema::TABLE);
    }
};
