<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Shipping\Schemas\Method\MethodSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(MethodSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->string(MethodSchema::CODE);
            $table->unique([TenantColumn::TENANT_ID, MethodSchema::CODE]);
            $table->boolean(MethodSchema::IS_ACTIVE)->default(true);
            $table->unsignedSmallInteger(MethodSchema::POSITION)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(MethodSchema::TABLE);
    }
};
