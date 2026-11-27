<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(CartSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent key for the layer-3 tenant-safe cart-item FK.
            $table->unique([TenantColumn::TENANT_ID, CartSchema::ID], 'carts_tenant_id_unique');
            // User carts key on user_id; guest carts key on token instead —
            // exactly one of the two is set (both nullable, both unique).
            $table->unsignedBigInteger(CartSchema::USER_ID)->nullable();
            $table->string(CartSchema::TOKEN)->nullable();
            $table->unique([TenantColumn::TENANT_ID, CartSchema::USER_ID]);
            $table->unique([TenantColumn::TENANT_ID, CartSchema::TOKEN]);
            // Coupon code (not an FK): keeps the cart decoupled from the
            // Discount module and lets a deactivated coupon be re-checked
            // at checkout; at most one coupon per cart
            $table->string(CartSchema::COUPON_CODE)->nullable();
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
