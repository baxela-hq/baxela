<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Cart\Schemas\Cart\CartSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CartSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // User carts key on user_id; guest carts key on token instead —
            // exactly one of the two is set (both nullable, both unique).
            $table->unsignedBigInteger(CartSchema::USER_ID)->nullable()->unique();
            $table->string(CartSchema::TOKEN)->nullable()->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(CartSchema::TABLE);
    }
};
