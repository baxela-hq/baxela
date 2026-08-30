<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Address\AddressTypeEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AddressSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(AddressSchema::USER_ID)->index();
            $table->enum(AddressSchema::TYPE, AddressTypeEnum::cases());
            $table->string(AddressSchema::FULL_NAME);
            $table->string(AddressSchema::PHONE);
            $table->string(AddressSchema::ADDRESS_LINE);
            $table->string(AddressSchema::CITY);
            $table->string(AddressSchema::POSTAL_CODE)->nullable();
            $table->char(AddressSchema::COUNTRY_CODE, 2)->nullable();
            $table->boolean(AddressSchema::IS_DEFAULT)->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AddressSchema::TABLE);
    }
};
