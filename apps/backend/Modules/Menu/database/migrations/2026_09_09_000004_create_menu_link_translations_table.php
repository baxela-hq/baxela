<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->foreignId(MenuLinkTranslationSchema::MENU_LINK_ID)
                ->constrained(MenuLinkSchema::TABLE)->onDelete('cascade');
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
