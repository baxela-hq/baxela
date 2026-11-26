<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TenantSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(TenantSchema::NAME);
            // The store's subdomain identity: unique, and never changed.
            $table->string(TenantSchema::SLUG)->unique();
            // The contact address is not unique: an owner running several
            // stores shares one address across them.
            $table->string(TenantSchema::EMAIL)->nullable()->index('tenant_tenants_email_idx');
            $table->string(TenantSchema::STATUS, 20)->default(TenantSchema::STATUS_ACTIVE);
            $table->index(TenantSchema::STATUS);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TenantSchema::TABLE);
    }
};
