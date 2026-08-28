<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(InventoryStockSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(InventoryStockSchema::VARIANT_ID)->unique();
            $table->unsignedSmallInteger(InventoryStockSchema::QUANTITY);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(InventoryStockSchema::TABLE);
    }
};
