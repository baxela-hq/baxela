<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(TelegramSubscriptionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            // Unique both ways: one Telegram chat per account, and a chat
            // is only ever linked to whoever presented the latest code.
            $table->unsignedBigInteger(TelegramSubscriptionSchema::USER_ID)->unique();
            $table->unsignedBigInteger(TelegramSubscriptionSchema::CHAT_ID)->unique();
            $table->string(TelegramSubscriptionSchema::USERNAME, 32)->nullable();
            $table->timestamp(TelegramSubscriptionSchema::LINKED_AT)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(TelegramSubscriptionSchema::TABLE);
    }
};
