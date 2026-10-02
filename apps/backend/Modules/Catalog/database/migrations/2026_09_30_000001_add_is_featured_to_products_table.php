<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\ProductSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(ProductSchema::TABLE, function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(ProductSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
