<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(OtpCodeSchema::TABLE, function (Blueprint $table) {
            // Failed-verification counter: an OTP is invalidated after
            // OtpCodeSchema::MAX_ATTEMPTS wrong codes, so guessing a 6-digit
            // code is infeasible even with distributed IP-based throttle
            // evasion.
            $table->unsignedSmallInteger(OtpCodeSchema::ATTEMPTS)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(OtpCodeSchema::TABLE, function (Blueprint $table) {
            $table->dropColumn(OtpCodeSchema::ATTEMPTS);
        });
    }
};
