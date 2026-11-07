<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliverySchema;
use Modules\Webhooks\Schemas\WebhookDelivery\WebhookDeliveryStatusEnum;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;
use Modules\Webhooks\Schemas\WebhookEvent\WebhookEventSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(WebhookDeliverySchema::TABLE, function (Blueprint $table) {
            $table->uuid(WebhookDeliverySchema::ID)->primary();
            $table->foreignId(WebhookDeliverySchema::ENDPOINT_ID)
                ->constrained(WebhookEndpointSchema::TABLE)->cascadeOnDelete();
            $table->uuid(WebhookDeliverySchema::EVENT_ID);
            $table->foreign(WebhookDeliverySchema::EVENT_ID)
                ->references(WebhookEventSchema::ID)
                ->on(WebhookEventSchema::TABLE)->cascadeOnDelete();
            $table->string(WebhookDeliverySchema::EVENT_TYPE)->index();
            $table->enum(WebhookDeliverySchema::STATUS, WebhookDeliveryStatusEnum::cases())
                ->default(WebhookDeliveryStatusEnum::PENDING->value)->index();
            // Claim/lease ownership token (gap-C2 D4): state updates are
            // conditional on it, closing the stale-worker race.
            $table->uuid(WebhookDeliverySchema::CLAIM_TOKEN)->nullable();
            $table->timestamp(WebhookDeliverySchema::CLAIMED_AT)->nullable();
            $table->unsignedInteger(WebhookDeliverySchema::ATTEMPT_COUNT)->default(0);
            $table->json(WebhookDeliverySchema::ATTEMPTS)->nullable();
            $table->timestamp(WebhookDeliverySchema::NEXT_ATTEMPT_AT)->nullable()->index();
            $table->smallInteger(WebhookDeliverySchema::RESPONSE_STATUS)->nullable();
            $table->string(WebhookDeliverySchema::RESPONSE_SNIPPET, 1000)->nullable();
            $table->string(WebhookDeliverySchema::ERROR_CATEGORY, 50)->nullable();
            $table->unsignedInteger(WebhookDeliverySchema::DURATION_MS)->nullable();
            $table->timestamp(WebhookDeliverySchema::DELIVERED_AT)->nullable();
            $table->timestamps();

            // Dispatch retries never duplicate delivery records (gap-C2 D4).
            $table->unique([
                WebhookDeliverySchema::ENDPOINT_ID,
                WebhookDeliverySchema::EVENT_ID,
            ], 'webhook_deliveries_endpoint_event_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(WebhookDeliverySchema::TABLE);
    }
};
