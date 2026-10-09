<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Post\PostImageCollectionEnum;
use Modules\Content\Schemas\Post\PostImageSchema;
use Modules\Content\Schemas\Post\PostSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PostImageSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PostImageSchema::POST_ID)->constrained(PostSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(PostImageSchema::MEDIA_ID)->index();
            $table->enum(PostImageSchema::COLLECTION, PostImageCollectionEnum::cases())->nullable();
            $table->string(PostImageSchema::URL, 500);
            $table->unsignedTinyInteger(PostImageSchema::POSITION)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PostImageSchema::TABLE);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
};
