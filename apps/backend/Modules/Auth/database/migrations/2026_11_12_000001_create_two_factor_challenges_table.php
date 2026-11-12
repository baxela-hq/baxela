<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Schemas\TwoFactorChallenge\TwoFactorChallengeSchema;
use Modules\Auth\Schemas\User\UserSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TwoFactorChallengeSchema::TABLE, function (Blueprint $table) {
            // SHA-256 hash of the raw token — the raw token is never stored.
            $table->char(TwoFactorChallengeSchema::TOKEN_HASH, 64)->primary();
            $table->foreignId(TwoFactorChallengeSchema::USER_ID)
                ->constrained(UserSchema::TABLE)
                ->cascadeOnDelete();
            $table->string(TwoFactorChallengeSchema::TYPE);
            $table->text(TwoFactorChallengeSchema::CONTEXT)->nullable();
            $table->unsignedInteger(TwoFactorChallengeSchema::FAILED_ATTEMPTS)->default(0);
            $table->timestamp(TwoFactorChallengeSchema::EXPIRES_AT);
            $table->timestamp(TwoFactorChallengeSchema::CONSUMED_AT)->nullable();
            $table->timestamps();

            $table->index([TwoFactorChallengeSchema::USER_ID, TwoFactorChallengeSchema::TYPE]);
            $table->index(TwoFactorChallengeSchema::EXPIRES_AT);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TwoFactorChallengeSchema::TABLE);
    }
};
