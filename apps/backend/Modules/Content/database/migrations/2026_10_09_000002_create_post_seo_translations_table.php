<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PostSeoTranslationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PostSeoTranslationSchema::POST_ID)
                ->constrained(PostSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(PostSeoTranslationSchema::LANGUAGE_ID)->nullable();
            $table->string(PostSeoTranslationSchema::META_TITLE)->nullable();
            $table->string(PostSeoTranslationSchema::META_DESCRIPTION)->nullable();
            $table->string(PostSeoTranslationSchema::OPEN_GRAPH_TITLE)->nullable();
            $table->string(PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION)->nullable();
            $table->timestamps();

            $table->unique([PostSeoTranslationSchema::POST_ID, PostSeoTranslationSchema::LANGUAGE_ID]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PostSeoTranslationSchema::TABLE);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
