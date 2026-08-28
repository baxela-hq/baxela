<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema as AVTSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AttributeValueSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(AttributeValueSchema::ATTRIBUTE_ID)
                ->constrained(AttributeSchema::TABLE)->onDelete('cascade');
            $table->unsignedSmallInteger(AttributeValueSchema::POSITION)->nullable();
            $table->timestamps();
        });

        Schema::create(AVTSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(AVTSchema::ATTRIBUTE_VALUE_ID)
                ->constrained(AttributeValueSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(AVTSchema::LANGUAGE_ID);
            $table->string(AVTSchema::TITLE);
            $table->timestamps();

            $table->unique([AVTSchema::ATTRIBUTE_VALUE_ID, AVTSchema::LANGUAGE_ID],
                AVTSchema::ATTRIBUTE_VALUE_ID.'_'.AVTSchema::LANGUAGE_ID.'_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AVTSchema::TABLE);
        Schema::dropIfExists(AttributeValueSchema::TABLE);
    }
};
