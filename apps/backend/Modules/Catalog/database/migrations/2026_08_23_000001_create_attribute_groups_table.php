<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AttributeGroupSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger(AttributeGroupSchema::POSITION)->nullable();
            $table->timestamps();
        });

        Schema::create(AttributeGroupTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(AttributeGroupTranslationSchema::ATTRIBUTE_GROUP_ID)
                ->constrained(AttributeGroupSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(AttributeGroupTranslationSchema::LANGUAGE_ID);
            $table->string(AttributeGroupTranslationSchema::TITLE);
            $table->timestamps();

            $table->unique([AttributeGroupTranslationSchema::ATTRIBUTE_GROUP_ID,
                AttributeGroupTranslationSchema::LANGUAGE_ID],
                AttributeGroupTranslationSchema::ATTRIBUTE_GROUP_ID.'_'.AttributeGroupTranslationSchema::LANGUAGE_ID.'_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AttributeGroupTranslationSchema::TABLE);
        Schema::dropIfExists(AttributeGroupSchema::TABLE);
    }
};
