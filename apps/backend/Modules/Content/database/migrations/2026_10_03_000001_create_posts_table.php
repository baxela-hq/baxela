<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PostSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->enum(PostSchema::STATUS, PostStatusEnum::cases());
            $table->boolean(PostSchema::IS_FEATURED)->default(false);
            $table->timestamps();
        });

        Schema::create(PostTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PostTranslationSchema::POST_ID)
                ->constrained(PostSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(PostTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(PostTranslationSchema::TITLE);
            $table->string(PostTranslationSchema::SLUG);
            $table->longText(PostTranslationSchema::CONTENT);
            $table->string(PostTranslationSchema::DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([PostTranslationSchema::POST_ID, PostTranslationSchema::LANGUAGE_ID]);
            $table->unique([PostTranslationSchema::LANGUAGE_ID, PostTranslationSchema::SLUG]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PostTranslationSchema::TABLE);
        Schema::dropIfExists(PostSchema::TABLE);
    }
};
