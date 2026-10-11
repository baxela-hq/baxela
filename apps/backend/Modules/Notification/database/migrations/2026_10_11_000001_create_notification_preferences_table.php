<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationChannelEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(NotificationPreferenceSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(NotificationPreferenceSchema::USER_ID)->index();
            $table->enum(NotificationPreferenceSchema::CODE, NotificationCodeEnum::cases())->index();
            $table->enum(NotificationPreferenceSchema::AUDIENCE, NotificationAudienceEnum::cases())->index();
            $table->enum(NotificationPreferenceSchema::CHANNEL, NotificationChannelEnum::cases())->index();
            $table->boolean(NotificationPreferenceSchema::IS_ENABLED)->default(true);
            $table->timestamps();

            // Rows are sparse overrides: absent = config-matrix default. The
            // audience column keeps an admin toggle from ever silencing the
            // same person's customer-facing notification of the same code.
            $table->unique([
                NotificationPreferenceSchema::USER_ID,
                NotificationPreferenceSchema::AUDIENCE,
                NotificationPreferenceSchema::CODE,
                NotificationPreferenceSchema::CHANNEL,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(NotificationPreferenceSchema::TABLE);
    }
};
