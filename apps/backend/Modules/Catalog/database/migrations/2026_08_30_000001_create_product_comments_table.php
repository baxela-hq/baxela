<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProductCommentSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(ProductCommentSchema::PRODUCT_ID)
                ->constrained(ProductSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(ProductCommentSchema::USER_ID)->index();
            $table->foreignId(ProductCommentSchema::PARENT_ID)->nullable()
                ->constrained(ProductCommentSchema::TABLE)->onDelete('cascade');
            $table->text(ProductCommentSchema::BODY);
            $table->enum(ProductCommentSchema::STATUS, ProductCommentStatusEnum::cases())
                ->default(ProductCommentStatusEnum::PENDING->value);
            $table->timestamps();

            $table->index([ProductCommentSchema::PRODUCT_ID, ProductCommentSchema::STATUS]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ProductCommentSchema::TABLE);
    }
};
