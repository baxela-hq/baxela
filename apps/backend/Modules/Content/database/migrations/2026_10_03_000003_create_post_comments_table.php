<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\PostComment\PostCommentSchema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PostCommentSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(PostCommentSchema::POST_ID)
                ->constrained(PostSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(PostCommentSchema::USER_ID)->index();
            $table->foreignId(PostCommentSchema::PARENT_ID)->nullable()
                ->constrained(PostCommentSchema::TABLE)->onDelete('cascade');
            $table->text(PostCommentSchema::BODY);
            $table->enum(PostCommentSchema::STATUS, PostCommentStatusEnum::cases())
                ->default(PostCommentStatusEnum::PENDING->value);
            $table->timestamps();

            $table->index([PostCommentSchema::POST_ID, PostCommentSchema::STATUS]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PostCommentSchema::TABLE);
    }
};
