<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Payment\Schemas\PaymentDriverCredential\PaymentDriverCredentialSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PaymentDriverCredentialSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->string(PaymentDriverCredentialSchema::DRIVER, 50);
            $table->string(PaymentDriverCredentialSchema::KEY, 100);
            // Encrypted at rest (marketplace P5.2 D1).
            $table->text(PaymentDriverCredentialSchema::VALUE);
            // Credentials are per store from day one (A2) — a (driver, key)
            // key without the tenant would let the first store to configure
            // a driver block every other store's configuration (review:
            // §4.5 class R, UNIQUE(tenant_id, driver, key)).
            $table->unique([
                TenantColumn::TENANT_ID,
                PaymentDriverCredentialSchema::DRIVER,
                PaymentDriverCredentialSchema::KEY,
            ]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PaymentDriverCredentialSchema::TABLE);
    }
};
