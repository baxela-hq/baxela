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
            $table->foreignId(TaxRateSchema::TAX_CLASS_ID)
                ->constrained(TaxClassSchema::TABLE)->onDelete('cascade');
            $table->decimal(TaxRateSchema::RATE, 7, 4)->unsigned();
            $table->boolean(TaxRateSchema::IS_ACTIVE)->default(true)->index();
            $table->timestamps();

            // one rate per country × class (name kept short for MySQL's
            // 64-char identifier limit)
            $table->unique(
                [TaxRateSchema::COUNTRY_CODE, TaxRateSchema::TAX_CLASS_ID],
                'tax_rates_country_class_unique'
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
