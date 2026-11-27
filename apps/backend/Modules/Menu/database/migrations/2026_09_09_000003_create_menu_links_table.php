<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(MenuLinkSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(MenuLinkSchema::MENU_ID);
            $table->foreign([TenantColumn::TENANT_ID, MenuLinkSchema::MENU_ID], 'menu_links_tenant_menu_fk')
                ->references([TenantColumn::TENANT_ID, MenuSchema::ID])
                ->on(MenuSchema::TABLE)->cascadeOnDelete();
            $table->unsignedBigInteger(MenuLinkSchema::PARENT_ID)->nullable()->index();
            $table->unsignedSmallInteger(MenuLinkSchema::POSITION)->nullable();
            $table->string(MenuLinkSchema::URL);
            $table->enum(MenuLinkSchema::TARGET, MenuLinkTargetEnum::cases());
            $table->timestamps();

            $table->index([MenuLinkSchema::MENU_ID, MenuLinkSchema::PARENT_ID]);
            // Composite parent key for the layer-3 tenant-safe translation FK.
            $table->unique([TenantColumn::TENANT_ID, MenuSchema::ID], 'menu_links_tenant_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(MenuLinkSchema::TABLE);
    }
};
