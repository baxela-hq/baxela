<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CategorySchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(CategorySchema::PARENT_ID)->nullable();
            $table->unsignedSmallInteger(CategorySchema::POSITION)->nullable();
            $table->timestamps();
        });

        Schema::create(CategoryTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(CategoryTranslationSchema::CATEGORY_ID)
                ->constrained(CategorySchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(CategoryTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(CategoryTranslationSchema::TITLE);
            $table->string(CategoryTranslationSchema::SLUG);
            $table->string(CategoryTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([CategoryTranslationSchema::CATEGORY_ID, CategoryTranslationSchema::LANGUAGE_ID]);
            $table->unique([CategoryTranslationSchema::LANGUAGE_ID, CategoryTranslationSchema::SLUG]);
        });

        Schema::create(CategoryProductSchema::TABLE, function (Blueprint $table) {
            $table->foreignId(CategoryProductSchema::PRODUCT_ID)
                ->constrained(ProductSchema::TABLE)->onDelete('cascade');

            $table->foreignId(CategoryProductSchema::CATEGORY_ID)
                ->constrained(CategorySchema::TABLE)->onDelete('cascade');

            $table->primary([CategoryProductSchema::PRODUCT_ID, CategoryProductSchema::CATEGORY_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //        Schema::table(CategoryProductSchema::TABLE, function (Blueprint $table) {
        //            $table->dropForeign([CategoryProductSchema::PRODUCT_ID, CategoryProductSchema::CATEGORY_ID]);
        //        });
        Schema::dropIfExists(CategoryProductSchema::TABLE);
        Schema::dropIfExists(CategoryTranslationSchema::TABLE);
        Schema::dropIfExists(CategorySchema::TABLE);
    }
};
