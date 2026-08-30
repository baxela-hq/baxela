<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shipping\Schemas\Zone\ZoneCountrySchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ZoneCountrySchema::TABLE, function (Blueprint $table) {
            $table->foreignId(ZoneCountrySchema::ZONE_ID)
                ->constrained(ZoneSchema::TABLE)->onDelete('cascade');
            $table->char(ZoneCountrySchema::COUNTRY_CODE, 2);

            $table->primary([ZoneCountrySchema::ZONE_ID, ZoneCountrySchema::COUNTRY_CODE]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ZoneCountrySchema::TABLE);
    }
};
