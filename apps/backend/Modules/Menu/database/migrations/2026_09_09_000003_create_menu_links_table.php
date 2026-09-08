<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->foreignId(MenuLinkSchema::MENU_ID)
                ->constrained(MenuSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(MenuLinkSchema::PARENT_ID)->nullable()->index();
            $table->unsignedSmallInteger(MenuLinkSchema::POSITION)->nullable();
            $table->string(MenuLinkSchema::URL);
            $table->enum(MenuLinkSchema::TARGET, MenuLinkTargetEnum::cases());
            $table->timestamps();

            $table->index([MenuLinkSchema::MENU_ID, MenuLinkSchema::PARENT_ID]);
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
