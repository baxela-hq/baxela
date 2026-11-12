<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Schemas\SignInLockout\SignInLockoutSchema;

return new class extends Migration
{
    /**
     * S.4 account lockout state (D2 rev 7) — keyed by the SHA-256 of the
     * canonical email, so unknown addresses get rows too and lockout
     * behaviour never discloses account existence (D9). No user FK on
     * purpose: failures are counted before credentials resolve.
     */
    public function up(): void
    {
        Schema::create(SignInLockoutSchema::TABLE, function (Blueprint $table) {
            $table->char(SignInLockoutSchema::EMAIL_HASH, 64)->primary();
            $table->unsignedInteger(SignInLockoutSchema::FAILED_ATTEMPTS)->default(0);
            $table->timestamp(SignInLockoutSchema::LAST_FAILED_AT)->nullable();
            $table->timestamp(SignInLockoutSchema::LOCKED_UNTIL)->nullable();
            $table->timestamp(SignInLockoutSchema::LOCKED_AT)->nullable();
            $table->timestamps();

            $table->index(SignInLockoutSchema::LOCKED_UNTIL);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(SignInLockoutSchema::TABLE);
    }
};
