<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantCurrency\TenantCurrencySchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TenantCurrencySchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantCurrencySchema::TENANT_ID);
            // No cross-module FK: the currency is validated through the Core gateway.
            $table->unsignedBigInteger(TenantCurrencySchema::CURRENCY_ID);
            $table->timestamps();

            $table->foreign(TenantCurrencySchema::TENANT_ID)
                ->references('id')->on(TenantSchema::TABLE)
                ->cascadeOnDelete();

            $table->unique(
                [TenantCurrencySchema::TENANT_ID, TenantCurrencySchema::CURRENCY_ID],
                'tenant_currencies_tenant_cur_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TenantCurrencySchema::TABLE);
    }
};
