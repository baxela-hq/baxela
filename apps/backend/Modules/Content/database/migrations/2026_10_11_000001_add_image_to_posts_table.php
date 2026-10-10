<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Post\PostSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(PostSchema::TABLE, function (Blueprint $table) {
            // Cover photo columns follow the categories precedent: plain
            // columns, no FK to the media table.
            $table->unsignedBigInteger(PostSchema::IMAGE_MEDIA_ID)->nullable();
            $table->string(PostSchema::IMAGE_URL, 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(PostSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn([PostSchema::IMAGE_MEDIA_ID, PostSchema::IMAGE_URL]);
        });
    }
};
