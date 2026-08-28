<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(VariantOptionValueSchema::TABLE, function (Blueprint $table) {
            $table->foreignId(VariantOptionValueSchema::OPTION_VALUE_ID)
                ->constrained(OptionValueSchema::TABLE)->onDelete('cascade');
            $table->foreignId(VariantOptionValueSchema::VARIANT_ID)
                ->constrained(VariantSchema::TABLE)->onDelete('cascade');
            $table->unique([VariantOptionValueSchema::OPTION_VALUE_ID,
                VariantOptionValueSchema::VARIANT_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //        Schema::table(VariantOptionValueSchema::TABLE, function (Blueprint $table) {
        //            $table->dropForeign([VariantOptionValueSchema::OPTION_VALUE_ID,
        //                VariantOptionValueSchema::VARIANT_ID]);
        //        });

        Schema::dropIfExists(VariantOptionValueSchema::TABLE);
    }
};
