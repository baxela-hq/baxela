<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Country\CountrySchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CountrySchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->char(CountrySchema::CODE, 2)->unique();
            $table->char(CountrySchema::CODE3, 3)->unique();
            $table->string(CountrySchema::NAME);
            $table->string(CountrySchema::NATIVE_NAME)->nullable();
            $table->string(CountrySchema::EMOJI, 10)->nullable();
            $table->string(CountrySchema::PHONE_CODE, 5)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CountrySchema::TABLE);
    }
};
