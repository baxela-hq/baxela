<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TaxClassSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent key for the layer-3 tenant-safe tax-rate FK.
            $table->unique([TenantColumn::TENANT_ID, TaxClassSchema::ID], 'tax_classes_tenant_id_unique');
            $table->string(TaxClassSchema::NAME);
            // The two single-flag invariants (exactly one default, exactly
            // one shipping class) are enforced transactionally by the admin
            // actions and guaranteed by the seeder
            $table->boolean(TaxClassSchema::IS_DEFAULT)->default(false)->index();
            $table->boolean(TaxClassSchema::IS_SHIPPING)->default(false)->index();
            $table->timestamps();
        });

        DB::statement(
            'CREATE UNIQUE INDEX tax_classes_one_default_unique ON '.TaxClassSchema::TABLE
            .' ('.TenantColumn::TENANT_ID.') WHERE '.TaxClassSchema::IS_DEFAULT
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TaxClassSchema::TABLE);
    }
};
