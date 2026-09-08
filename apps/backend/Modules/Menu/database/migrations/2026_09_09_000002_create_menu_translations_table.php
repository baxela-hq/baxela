<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(MenuTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(MenuTranslationSchema::MENU_ID)
                ->constrained(MenuSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(MenuTranslationSchema::LANGUAGE_ID);
            $table->string(MenuTranslationSchema::TITLE);
            $table->string(MenuTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([MenuTranslationSchema::MENU_ID, MenuTranslationSchema::LANGUAGE_ID],
                MenuTranslationSchema::MENU_ID.'_'.MenuTranslationSchema::LANGUAGE_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(MenuTranslationSchema::TABLE);
    }
};
