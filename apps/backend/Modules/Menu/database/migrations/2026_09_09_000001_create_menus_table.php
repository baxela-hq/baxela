<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Menu\Schemas\Menu\MenuSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(MenuSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent key for the layer-3 tenant-safe menu-link FK.
            $table->unique([TenantColumn::TENANT_ID, MenuSchema::ID], 'menu_menus_tenant_id_unique');
            $table->string(MenuSchema::LOCATION);
            $table->unique([TenantColumn::TENANT_ID, MenuSchema::LOCATION]);
            $table->boolean(MenuSchema::IS_ACTIVE)->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(MenuSchema::TABLE);
    }
};
