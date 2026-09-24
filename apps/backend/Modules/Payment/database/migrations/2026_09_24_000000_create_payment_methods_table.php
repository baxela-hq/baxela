<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
            $table->string(PaymentMethodSchema::METHOD)->unique();
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
