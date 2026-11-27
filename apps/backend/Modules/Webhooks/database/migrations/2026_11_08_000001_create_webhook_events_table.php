<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
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
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent key for the layer-3 tenant-safe delivery FK.
            $table->unique([TenantColumn::TENANT_ID, WebhookEventSchema::ID], 'webhook_events_tenant_id_unique');
            // Deterministic business identity — dedupes repeated listener
            // invocations of the same transition (gap-C2 D2).
            $table->string(WebhookEventSchema::SOURCE_KEY);
            $table->unique([TenantColumn::TENANT_ID, WebhookEventSchema::SOURCE_KEY]);
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
