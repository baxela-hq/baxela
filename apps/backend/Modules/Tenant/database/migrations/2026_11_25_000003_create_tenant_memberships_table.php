<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TenantMembershipSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantMembershipSchema::TENANT_ID);
            // No cross-module FK: the user is validated through the Auth gateway.
            $table->unsignedBigInteger(TenantMembershipSchema::USER_ID);
            $table->string(TenantMembershipSchema::STATUS, 20)->default(TenantMembershipSchema::STATUS_ACTIVE);
            $table->boolean(TenantMembershipSchema::IS_OWNER)->default(false);
            $table->timestamp(TenantMembershipSchema::JOINED_AT)->nullable();
            $table->timestamps();

            // Intra-module FK (safe); the user side deliberately has none.
            $table->foreign(TenantMembershipSchema::TENANT_ID)
                ->references('id')->on(TenantSchema::TABLE)
                ->cascadeOnDelete();

            $table->unique(
                [TenantMembershipSchema::TENANT_ID, TenantMembershipSchema::USER_ID],
                'tenant_memberships_tenant_user_unique',
            );
            $table->index(TenantMembershipSchema::USER_ID, 'tenant_memberships_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TenantMembershipSchema::TABLE);
    }
};
