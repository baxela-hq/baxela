<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(AuditLogSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantColumn::TENANT_ID)->nullable();
            $table->index(TenantColumn::TENANT_ID);
            $table->unsignedBigInteger(AuditLogSchema::USER_ID)->nullable();
            $table->string(AuditLogSchema::MODULE);
            $table->string(AuditLogSchema::ENTITY);
            $table->string(AuditLogSchema::ACTION);
            $table->unsignedBigInteger(AuditLogSchema::ENTITY_ID)->nullable();
            $table->json(AuditLogSchema::PROPERTIES)->nullable();
            $table->string(AuditLogSchema::IP_ADDRESS)->nullable();
            $table->string(AuditLogSchema::USER_AGENT)->nullable();
            $table->timestamps();

            $table->index(AuditLogSchema::USER_ID);
            $table->index(AuditLogSchema::MODULE);
            $table->index(AuditLogSchema::CREATED_AT);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(AuditLogSchema::TABLE);
    }
};
