<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\User\Schemas\Address\AddressSchema;

return new class extends Migration
{
    /**
     * VAT identifier for business customers (gap-C1c D1): collection at
     * the address, offline format validation only — never a claim of
     * registration verification.
     */
    public function up(): void
    {
        Schema::table(AddressSchema::TABLE, function (Blueprint $table): void {
            $table->string(AddressSchema::VAT_ID, 32)->nullable()->after(AddressSchema::REGION_CODE);
        });
    }

    public function down(): void
    {
        Schema::table(AddressSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(AddressSchema::VAT_ID);
        });
    }
};
