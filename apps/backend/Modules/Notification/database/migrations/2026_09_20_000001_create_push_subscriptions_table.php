<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(PushSubscriptionSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(PushSubscriptionSchema::USER_ID)->index();
            // 500 chars keeps the unique index under MySQL's 3072-byte key
            // limit on utf8mb4; real push-service endpoints stay well below.
            $table->string(PushSubscriptionSchema::ENDPOINT, 500)->unique();
            $table->string(PushSubscriptionSchema::P256DH)->nullable();
            $table->string(PushSubscriptionSchema::AUTH)->nullable();
            $table->string(PushSubscriptionSchema::USER_AGENT, 500)->nullable();
            $table->string(PushSubscriptionSchema::LOCALE, 12)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(PushSubscriptionSchema::TABLE);
    }
};
