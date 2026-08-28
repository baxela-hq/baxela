<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\IdempotencyKeys\IdempotencyKeysSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(IdempotencyKeysSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(IdempotencyKeysSchema::USER_ID)->nullable();
            $table->string(IdempotencyKeysSchema::KEY)->unique();
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
