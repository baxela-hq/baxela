<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PaymentMethodSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->string(PaymentMethodSchema::METHOD);
            $table->unique([TenantColumn::TENANT_ID, PaymentMethodSchema::METHOD]);
            $table->boolean(PaymentMethodSchema::IS_ACTIVE)->default(false)->index();
            $table->unsignedInteger(PaymentMethodSchema::SORT_ORDER)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PaymentMethodSchema::TABLE);
    }
};
