<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\User\Schemas\Address\AddressSchema;

return new class extends Migration
{
    /**
     * Regional tax plumbing (gap-C1a D3). Nullable: stored customer
     * addresses and historical rows are never re-validated against the
     * seed — only NEW input is pair-validated (RegionCodeRule).
     */
    public function up(): void
    {
        Schema::table(AddressSchema::TABLE, function (Blueprint $table): void {
            $table->string(AddressSchema::REGION_CODE, 10)->nullable()->after(AddressSchema::COUNTRY_CODE);
        });
    }

    public function down(): void
    {
        Schema::table(AddressSchema::TABLE, function (Blueprint $table): void {
            $table->dropColumn(AddressSchema::REGION_CODE);
        });
    }
};
