<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OptionValueSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(OptionValueSchema::OPTION_ID)
                ->constrained(OptionSchema::TABLE)->onDelete('cascade');
            $table->unsignedSmallInteger(OptionValueSchema::POSITION)->nullable();
            $table->timestamps();
        });

        Schema::create(OVTSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(OVTSchema::OPTION_VALUE_ID)
                ->constrained(OptionValueSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(OVTSchema::LANGUAGE_ID);
            $table->string(OVTSchema::TITLE);
            $table->string(OVTSchema::SLUG);
            $table->timestamps();

            $table->unique([OVTSchema::OPTION_VALUE_ID, OVTSchema::LANGUAGE_ID],
                OVTSchema::OPTION_VALUE_ID.'_'.OVTSchema::LANGUAGE_ID);
            $table->unique([OVTSchema::LANGUAGE_ID, OVTSchema::SLUG]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(OptionValueSchema::TABLE, function (Blueprint $table) {
            $table->dropForeign([OptionValueSchema::OPTION_ID]);
        });
        Schema::table(OVTSchema::TABLE, function (Blueprint $table) {
            $table->dropForeign([OVTSchema::OPTION_VALUE_ID]);
        });
        Schema::dropIfExists(OVTSchema::TABLE);
        Schema::dropIfExists(OptionValueSchema::TABLE);
    }
};
