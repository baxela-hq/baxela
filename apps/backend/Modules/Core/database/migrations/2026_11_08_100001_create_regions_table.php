<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Region\RegionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(RegionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->char(RegionSchema::COUNTRY_CODE, 2)->index();
            // Canonical ISO 3166-2 form, e.g. 'US-CA', 'CA-ON', 'DE-BY'.
            $table->string(RegionSchema::CODE, 10)->unique();
            $table->string(RegionSchema::NAME);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(RegionSchema::TABLE);
    }
};
