<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentStatusEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(ShipmentSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // Plain column on purpose: orders live in another module (cross-module convention).
            $table->unsignedBigInteger(ShipmentSchema::ORDER_ID)->index();
            $table->string(ShipmentSchema::CARRIER_NAME)->nullable();
            $table->string(ShipmentSchema::TRACKING_NUMBER)->nullable();
            $table->string(ShipmentSchema::TRACKING_URL)->nullable();
            $table->enum(ShipmentSchema::STATUS, ShipmentStatusEnum::cases())->default(ShipmentStatusEnum::PENDING);
            $table->timestamp(ShipmentSchema::SHIPPED_AT)->nullable();
            $table->timestamp(ShipmentSchema::DELIVERED_AT)->nullable();
            $table->text(ShipmentSchema::NOTES)->nullable();
            $table->timestamps();

            $table->unique(ShipmentSchema::ORDER_ID);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(ShipmentSchema::TABLE);
    }
};
