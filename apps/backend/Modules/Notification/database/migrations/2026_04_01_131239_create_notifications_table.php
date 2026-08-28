<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(NotificationSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(NotificationSchema::USER_ID)->index()->nullable();
            $table->enum(NotificationSchema::CODE, NotificationCodeEnum::cases())->index();
            $table->enum(NotificationSchema::AUDIENCE, NotificationAudienceEnum::cases())->index();
            $table->string(NotificationSchema::TITLE);
            $table->string(NotificationSchema::BODY, 500);
            $table->json(NotificationSchema::META)->nullable();
            $table->timestamp(NotificationSchema::READ_AT)->nullable();
            $table->timestamp(NotificationSchema::CREATED_AT);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(NotificationSchema::TABLE);
    }
};
