<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressTypeEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OrderAddressSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->foreignId(OrderAddressSchema::ORDER_ID)->constrained(OrderSchema::TABLE)->onDelete('cascade');
            $table->enum(OrderAddressSchema::TYPE, OrderAddressTypeEnum::cases());
            $table->string(OrderAddressSchema::FULL_NAME);
            $table->string(OrderAddressSchema::PHONE);
            $table->string(OrderAddressSchema::ADDRESS_LINE);
            $table->string(OrderAddressSchema::CITY);
            $table->string(OrderAddressSchema::POSTAL_CODE)->nullable();
            $table->char(OrderAddressSchema::COUNTRY_CODE, 2)->nullable();
            $table->timestamp(OrderAddressSchema::CREATED_AT);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OrderAddressSchema::TABLE);
    }
};
