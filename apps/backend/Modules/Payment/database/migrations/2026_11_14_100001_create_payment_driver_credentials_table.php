<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->string(PaymentDriverCredentialSchema::DRIVER, 50);
            $table->string(PaymentDriverCredentialSchema::KEY, 100);
            // Encrypted at rest (marketplace P5.2 D1).
            $table->text(PaymentDriverCredentialSchema::VALUE);
            $table->unique([PaymentDriverCredentialSchema::DRIVER, PaymentDriverCredentialSchema::KEY]);
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
