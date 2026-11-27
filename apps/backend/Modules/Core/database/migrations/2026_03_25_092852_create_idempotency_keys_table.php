<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(IdempotencyKeysSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(IdempotencyKeysSchema::USER_ID)->nullable();
            $table->string(IdempotencyKeysSchema::KEY);
            // Phase 2 closes the cross-tenant collision (§10 row 31); Phase 4
            // widens this to (tenant_id, principal_key, key) with the full
            // reservation protocol of §6.6.
            $table->unique(
                [TenantColumn::TENANT_ID, IdempotencyKeysSchema::KEY],
                'core_idempotency_keys_tenant_key_unique',
            );
            $table->json(IdempotencyKeysSchema::RESPONSE)->nullable();
            $table->timestamp(IdempotencyKeysSchema::EXPIRED_AT)->nullable();
            $table->timestamp(IdempotencyKeysSchema::CREATED_AT);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(IdempotencyKeysSchema::TABLE);
    }
};
