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
            // NOT NULL for every caller (§6.6): authenticated users are
            // user:{id}, guests are guest:{sha256(X-Cart-Token)}. NULL would
            // let the composite unique admit duplicate guest claims.
            $table->string(IdempotencyKeysSchema::PRINCIPAL_KEY);
            $table->string(IdempotencyKeysSchema::KEY);
            $table->enum(IdempotencyKeysSchema::STATUS, [
                IdempotencyKeysSchema::STATUS_PROCESSING,
                IdempotencyKeysSchema::STATUS_COMPLETED,
            ])->default(IdempotencyKeysSchema::STATUS_PROCESSING);
            $table->string(IdempotencyKeysSchema::CLAIM_TOKEN)->nullable();
            $table->json(IdempotencyKeysSchema::RESPONSE)->nullable();
            $table->timestamp(IdempotencyKeysSchema::EXPIRES_AT)->nullable();
            // A committed claim is never reclaimed: the marker is written in
            // the business transaction, so a non-null stamp means durable
            // work happened even if the response was never stored.
            $table->timestamp(IdempotencyKeysSchema::COMMITTED_AT)->nullable();
            $table->timestamp(IdempotencyKeysSchema::CREATED_AT);

            // Phase 2 closed the cross-tenant collision with a two-column
            // unique; Phase 4 widens it to the stable logical request
            // identity from §6.6 (A1: the original migration is edited).
            $table->unique(
                [TenantColumn::TENANT_ID, IdempotencyKeysSchema::PRINCIPAL_KEY, IdempotencyKeysSchema::KEY],
                'core_idempotency_keys_tenant_principal_key_unique',
            );
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
