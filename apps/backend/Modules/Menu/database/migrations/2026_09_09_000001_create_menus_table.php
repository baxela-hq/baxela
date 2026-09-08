<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->string(MenuSchema::LOCATION)->unique();
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
