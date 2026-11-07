<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(WebhookEventSchema::TABLE, function (Blueprint $table) {
            $table->uuid(WebhookEventSchema::ID)->primary();
            // Deterministic business identity — dedupes repeated listener
            // invocations of the same transition (gap-C2 D2).
            $table->string(WebhookEventSchema::SOURCE_KEY)->unique();
            $table->string(WebhookEventSchema::EVENT_TYPE)->index();
            $table->json(WebhookEventSchema::PAYLOAD);
            $table->timestamp(WebhookEventSchema::PROCESSED_AT)->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(WebhookEventSchema::TABLE);
    }
};
