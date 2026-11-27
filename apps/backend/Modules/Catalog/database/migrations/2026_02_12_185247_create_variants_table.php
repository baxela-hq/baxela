<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(VariantSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(VariantSchema::PRODUCT_ID);
            $table->foreign([TenantColumn::TENANT_ID, VariantSchema::PRODUCT_ID], 'catalog_variants_tenant_product_fk')
                ->references([TenantColumn::TENANT_ID, ProductSchema::ID])
                ->on(ProductSchema::TABLE)->cascadeOnDelete();
            $table->string(VariantSchema::SKU);
            $table->string(VariantSchema::BARCODE)->nullable();
            $table->decimal(VariantSchema::PRICE, 12, 2)->unsigned()->default(0.00);
            $table->unsignedSmallInteger(VariantSchema::QUANTITY)->default(0);
            $table->decimal(VariantSchema::COMPARE_PRICE, 12, 2)->unsigned()->nullable();
            $table->decimal(VariantSchema::COST_PRICE, 12, 2)->unsigned()->nullable();
            $table->boolean(VariantSchema::IS_DEFAULT)->default(false);
            $table->timestamps();
        });

        // Functional case-insensitive uniques (postgres-migration DD2);
        // nullable barcode keeps multiple NULLs legal on every engine.
        $mysql = DB::connection()->getDriverName() === 'mysql';
        $skuSql = 'CREATE UNIQUE INDEX variants_sku_lower_unique ON '.VariantSchema::TABLE.' '.(
            $mysql ? '('.TenantColumn::TENANT_ID.', (LOWER('.VariantSchema::SKU.')))' : '('.TenantColumn::TENANT_ID.', LOWER('.VariantSchema::SKU.'))'
        );
        $barcodeSql = 'CREATE UNIQUE INDEX variants_barcode_lower_unique ON '.VariantSchema::TABLE.' '.(
            $mysql ? '('.TenantColumn::TENANT_ID.', (LOWER('.VariantSchema::BARCODE.')))' : '('.TenantColumn::TENANT_ID.', LOWER('.VariantSchema::BARCODE.'))'
        );
        DB::statement($skuSql);
        DB::statement($barcodeSql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Plain dropIfExists: a dropForeign rebuild cannot round-trip the
        // functional LOWER() expression indexes on SQLite (Laravel's
        // BlueprintState reads index columns via pragma_index_info, which
        // yields none for expression indexes); dropIfExists removes the
        // table and its FK together on every engine.
        Schema::dropIfExists(VariantSchema::TABLE);
    }
};
