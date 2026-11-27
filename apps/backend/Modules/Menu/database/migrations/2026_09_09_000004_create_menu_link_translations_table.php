<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(MenuLinkTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(MenuLinkTranslationSchema::MENU_LINK_ID);
            $table->foreign([TenantColumn::TENANT_ID, MenuLinkTranslationSchema::MENU_LINK_ID], 'menu_link_translations_tenant_link_fk')
                ->references([TenantColumn::TENANT_ID, MenuLinkSchema::ID])
                ->on(MenuLinkSchema::TABLE)->cascadeOnDelete();
            $table->unsignedBigInteger(MenuLinkTranslationSchema::LANGUAGE_ID);
            $table->string(MenuLinkTranslationSchema::TITLE);
            $table->string(MenuLinkTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([MenuLinkTranslationSchema::MENU_LINK_ID, MenuLinkTranslationSchema::LANGUAGE_ID],
                MenuLinkTranslationSchema::MENU_LINK_ID.'_'.MenuLinkTranslationSchema::LANGUAGE_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(MenuLinkTranslationSchema::TABLE);
    }
};
