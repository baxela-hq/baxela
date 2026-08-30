<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(RateSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(RateSchema::METHOD_ID)
                ->constrained(MethodSchema::TABLE)->onDelete('cascade');
            $table->foreignId(RateSchema::ZONE_ID)
                ->constrained(ZoneSchema::TABLE)->onDelete('cascade');
            $table->decimal(RateSchema::PRICE, 12, 2)->unsigned()->default(0.00);
            $table->timestamps();

            $table->unique([RateSchema::METHOD_ID, RateSchema::ZONE_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(RateSchema::TABLE);
    }
};
