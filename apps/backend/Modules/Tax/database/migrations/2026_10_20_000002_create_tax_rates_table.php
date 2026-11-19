<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TaxRateSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(TaxRateSchema::NAME);
            $table->char(TaxRateSchema::COUNTRY_CODE, 2)->index();
            // Regional tax rates (gap-C1a D1). region_code is a NOT NULL
            // scope key: '' is the country-wide scope — a real value makes
            // the database enforce one-rate-per-scope on BOTH engines (a
            // nullable column would allow duplicate country-wide rows).
            $table->string(TaxRateSchema::REGION_CODE, 10)->default('');
            $table->foreignId(TaxRateSchema::TAX_CLASS_ID)
                ->constrained(TaxClassSchema::TABLE)->onDelete('cascade');
            $table->decimal(TaxRateSchema::RATE, 7, 4)->unsigned();
            $table->boolean(TaxRateSchema::IS_ACTIVE)->default(true)->index();
            $table->timestamps();

            // one rate per country × region × class (name kept short for
            // MySQL's 64-char identifier limit)
            $table->unique(
                [TaxRateSchema::COUNTRY_CODE, TaxRateSchema::REGION_CODE, TaxRateSchema::TAX_CLASS_ID],
                'tax_rates_country_region_class_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TaxRateSchema::TABLE);
    }
};
