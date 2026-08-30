<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ZoneSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(ZoneSchema::NAME);
            $table->boolean(ZoneSchema::IS_ACTIVE)->default(true);
            $table->unsignedSmallInteger(ZoneSchema::POSITION)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ZoneSchema::TABLE);
    }
};
