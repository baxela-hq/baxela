<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TenantDomainSchema::TABLE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger(TenantDomainSchema::TENANT_ID);
            $table->string(TenantDomainSchema::DOMAIN)->unique();
            $table->string(TenantDomainSchema::STATUS, 20)->default(TenantDomainSchema::STATUS_PENDING);
            $table->boolean(TenantDomainSchema::IS_PRIMARY)->default(false);
            $table->timestamp(TenantDomainSchema::VERIFIED_AT)->nullable();
            $table->string(TenantDomainSchema::VERIFICATION_TOKEN_HASH, 64)->nullable();
            $table->string(TenantDomainSchema::VERIFICATION_METHOD, 10)->nullable();
            $table->timestamp(TenantDomainSchema::VERIFICATION_EXPIRES_AT)->nullable();
            $table->timestamp(TenantDomainSchema::RELEASED_AT)->nullable();
            $table->timestamp(TenantDomainSchema::COOLING_OFF_UNTIL)->nullable();
            $table->timestamps();

            // Intra-module: safe to reference the tenants table here.
            $table->foreign(TenantDomainSchema::TENANT_ID)
                ->references('id')->on(TenantSchema::TABLE)
                ->cascadeOnDelete();
            $table->index(TenantDomainSchema::TENANT_ID);
        });

        // At most one primary domain per tenant — the plain unique on
        // domain cannot express this. A partial unique index works on
        // PostgreSQL and SQLite alike (multi-tenancy §4.1). Written as a
        // raw statement: Blueprint::rawIndex wraps its argument as an
        // index *expression*, not a whole statement.
        DB::statement(
            'CREATE UNIQUE INDEX tenant_domains_one_primary_unique ON '.TenantDomainSchema::TABLE
            .' ('.TenantDomainSchema::TENANT_ID.') WHERE '.TenantDomainSchema::IS_PRIMARY,
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(TenantDomainSchema::TABLE);
    }
};
