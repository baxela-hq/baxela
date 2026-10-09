<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\PostCategory\PostCategoryPostSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PostCategorySchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(PostCategorySchema::PARENT_ID)->nullable();
            $table->unsignedSmallInteger(PostCategorySchema::POSITION)->nullable();
            $table->timestamps();
        });

        Schema::create(PostCategoryTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PostCategoryTranslationSchema::POST_CATEGORY_ID)
                ->constrained(PostCategorySchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(PostCategoryTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(PostCategoryTranslationSchema::TITLE);
            $table->string(PostCategoryTranslationSchema::SLUG);
            $table->string(PostCategoryTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            // Laravel's generated index name exceeds MySQL's 64-char
            // identifier limit on this table, so both uniques are named
            // explicitly.
            $table->unique(
                [PostCategoryTranslationSchema::POST_CATEGORY_ID, PostCategoryTranslationSchema::LANGUAGE_ID],
                'content_post_cat_trans_cat_lang_unique'
            );
            $table->unique(
                [PostCategoryTranslationSchema::LANGUAGE_ID, PostCategoryTranslationSchema::SLUG],
                'content_post_cat_trans_lang_slug_unique'
            );
        });

        Schema::create(PostCategoryPostSchema::TABLE, function (Blueprint $table) {
            $table->foreignId(PostCategoryPostSchema::POST_ID)
                ->constrained(PostSchema::TABLE)->onDelete('cascade');

            $table->foreignId(PostCategoryPostSchema::POST_CATEGORY_ID)
                ->constrained(PostCategorySchema::TABLE)->onDelete('cascade');

            $table->primary([PostCategoryPostSchema::POST_ID, PostCategoryPostSchema::POST_CATEGORY_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PostCategoryPostSchema::TABLE);
        Schema::dropIfExists(PostCategoryTranslationSchema::TABLE);
        Schema::dropIfExists(PostCategorySchema::TABLE);
    }
};
