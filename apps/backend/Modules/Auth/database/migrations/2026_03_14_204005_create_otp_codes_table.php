<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Schemas\Otp\OtpCodeActionEnum;
use Modules\Auth\Schemas\Otp\OtpCodeSchema;
use Modules\Auth\Schemas\Otp\OtpCodeTypeEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(OtpCodeSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(OtpCodeSchema::MOBILE, 11)->index()->nullable();
            $table->string(OtpCodeSchema::EMAIL)->index()->nullable();
            $table->enum(OtpCodeSchema::TYPE, OtpCodeTypeEnum::cases());
            $table->enum(OtpCodeSchema::ACTION, OtpCodeActionEnum::cases());
            $table->string(OtpCodeSchema::CODE);
            $table->timestamp(OtpCodeSchema::EXPIRES_AT);
            $table->boolean(OtpCodeSchema::IS_USED)->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(OtpCodeSchema::TABLE);
    }
};
