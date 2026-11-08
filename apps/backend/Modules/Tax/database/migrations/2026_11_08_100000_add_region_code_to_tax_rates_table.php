<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

return new class extends Migration
{
    /**
     * Regional tax rates (gap-C1a D1). region_code is a NOT NULL scope
     * key: '' is the country-wide scope — a real value makes the
     * database enforce one-rate-per-scope on BOTH engines (a nullable
     * column would allow duplicate country-wide rows).
     *
     * ROLLBACK WARNING: down() drops region-specific rates. Do not roll
     * back after regional rates exist in production unless they were
     * exported or the loss is accepted (gap-phase-c-engines §5 D6).
     */
    public function up(): void
    {
        Schema::table(TaxRateSchema::TABLE, function (Blueprint $table): void {
            $table->string(TaxRateSchema::REGION_CODE, 10)->default('')->after(TaxRateSchema::COUNTRY_CODE);
        });

        Schema::table(TaxRateSchema::TABLE, function (Blueprint $table): void {
            // The previous (country_code, tax_class_id) unique guarantees
            // no duplicates exist among '' rows — the swap is dedup-safe.
            $table->dropUnique('tax_rates_country_class_unique');
        });

        Schema::table(TaxRateSchema::TABLE, function (Blueprint $table): void {
            $table->unique(
                [TaxRateSchema::COUNTRY_CODE, TaxRateSchema::REGION_CODE, TaxRateSchema::TAX_CLASS_ID],
                'tax_rates_country_region_class_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table(TaxRateSchema::TABLE, function (Blueprint $table): void {
            $table->dropUnique('tax_rates_country_region_class_unique');
        });

        // Region-specific rates lose their uniqueness anchor and are
        // deleted — see the ROLLBACK WARNING above.
        Schema::table(TaxRateSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(TaxRateSchema::REGION_CODE);
        });

        Schema::table(TaxRateSchema::TABLE, function (Blueprint $table): void {
            $table->unique(
                [TaxRateSchema::COUNTRY_CODE, TaxRateSchema::TAX_CLASS_ID],
                'tax_rates_country_class_unique',
            );
        });
    }
};
