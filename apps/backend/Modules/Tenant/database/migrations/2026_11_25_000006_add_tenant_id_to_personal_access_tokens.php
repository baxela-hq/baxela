<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;

return new class extends Migration
{
    /**
     * Machine tokens are issued for exactly one store (multi-tenancy §9.7).
     * The tenant lives on the token row so the machine audience resolves it
     * from the bearer token itself — never from a header. Ordinary user
     * tokens carry no tenant (NULL); the column is a lookup key, not store
     * ownership, so personal_access_tokens stays an installation-level table.
     *
     * A dedicated migration rather than an in-place edit: the create
     * migration is Laravel's own shipped file.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger(TenantColumn::TENANT_ID)->nullable();
        });

        // Partial index: only machine tokens carry a tenant, so it stays
        // tiny and both engines support the WHERE clause.
        DB::statement(
            'CREATE INDEX personal_access_tokens_tenant_idx ON personal_access_tokens ('
            .TenantColumn::TENANT_ID.') WHERE '.TenantColumn::TENANT_ID.' IS NOT NULL',
        );
    }

    public function down(): void
    {
        // The partial index references the column being dropped: SQLite
        // refuses the drop while the index exists. `IF EXISTS` is portable.
        DB::statement('DROP INDEX IF EXISTS personal_access_tokens_tenant_idx');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(TenantColumn::TENANT_ID);
        });
    }
};
