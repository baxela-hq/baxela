<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(WishlistItemSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // One implicit wishlist per user: no parent wishlists table,
            // the unique pair makes (user, product) the natural key.
            $table->unsignedBigInteger(WishlistItemSchema::USER_ID)->index();
            $table->unsignedBigInteger(WishlistItemSchema::PRODUCT_ID)->index();
            $table->unique([WishlistItemSchema::USER_ID, WishlistItemSchema::PRODUCT_ID]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(WishlistItemSchema::TABLE);
    }
};
