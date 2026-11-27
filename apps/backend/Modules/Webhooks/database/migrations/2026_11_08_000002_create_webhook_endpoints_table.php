<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Webhooks\Schemas\WebhookEndpoint\WebhookEndpointSchema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(WebhookEndpointSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID);
            $table->index(TenantColumn::TENANT_ID);
            // Composite parent key for the layer-3 tenant-safe delivery FK.
            $table->unique([TenantColumn::TENANT_ID, WebhookEndpointSchema::ID], 'webhook_endpoints_tenant_id_unique');
            $table->string(WebhookEndpointSchema::NAME);
            $table->string(WebhookEndpointSchema::URL, 500);
            // Encrypted at rest (gap-C2 D8).
            $table->text(WebhookEndpointSchema::SECRET);
            $table->json(WebhookEndpointSchema::EVENTS);
            $table->boolean(WebhookEndpointSchema::IS_ACTIVE)->default(true)->index();
            // Archive-not-delete: delivery history must survive (gap-C2 D12).
            $table->boolean(WebhookEndpointSchema::IS_ARCHIVED)->default(false)->index();
            $table->unsignedInteger(WebhookEndpointSchema::CONSECUTIVE_FAILURES)->default(0);
            $table->unsignedBigInteger(WebhookEndpointSchema::CREATED_BY)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(WebhookEndpointSchema::TABLE);
    }
};
