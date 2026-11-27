<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ProductCommentReactionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->foreignId(ProductCommentReactionSchema::PRODUCT_COMMENT_ID)
                ->constrained(ProductCommentSchema::TABLE)->onDelete('cascade');
            $table->unsignedBigInteger(ProductCommentReactionSchema::USER_ID)->index();
            $table->enum(ProductCommentReactionSchema::REACTION, ProductCommentReactionEnum::cases());
            $table->timestamps();

            // one reaction per user per comment (name kept short for MySQL's
            // 64-char identifier limit)
            $table->unique([
                ProductCommentReactionSchema::PRODUCT_COMMENT_ID,
                ProductCommentReactionSchema::USER_ID,
            ], 'catalog_pcr_comment_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ProductCommentReactionSchema::TABLE);
    }
};
