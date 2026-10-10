<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(BrandSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger(BrandSchema::POSITION)->nullable();
            $table->unsignedBigInteger(BrandSchema::IMAGE_MEDIA_ID)->nullable();
            $table->string(BrandSchema::IMAGE_URL, 500)->nullable();
            $table->timestamps();
        });

        Schema::create(BrandTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(BrandTranslationSchema::BRAND_ID)
                ->constrained(BrandSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(BrandTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(BrandTranslationSchema::TITLE);
            $table->string(BrandTranslationSchema::SLUG);
            $table->string(BrandTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([BrandTranslationSchema::BRAND_ID, BrandTranslationSchema::LANGUAGE_ID]);
            $table->unique([BrandTranslationSchema::LANGUAGE_ID, BrandTranslationSchema::SLUG]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(BrandTranslationSchema::TABLE);
        Schema::dropIfExists(BrandSchema::TABLE);
    }
};
