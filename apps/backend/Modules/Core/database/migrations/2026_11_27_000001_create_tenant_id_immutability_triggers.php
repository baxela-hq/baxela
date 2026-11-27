<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantTableInventory;

return new class extends Migration
{
    /**
     * The explicit Phase 2 inventory, not a guess from column names.
     * Key-only tables (machine tokens, spatie team tables, tenant metadata)
     * and the nine tenant-less pivots are excluded (row 113).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $column = TenantColumn::TENANT_ID;

        if ($driver === 'pgsql') {
            DB::statement("CREATE OR REPLACE FUNCTION baxela_forbid_tenant_id_change() RETURNS trigger AS \$\$ BEGIN IF NEW.{$column} IS DISTINCT FROM OLD.{$column} THEN RAISE EXCEPTION 'tenant_id is immutable'; END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql;");
        }

        foreach (TenantTableInventory::storeOwned() as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tenant immutability table [{$table}] is missing.");
            }
            if (! Schema::hasColumn($table, $column)) {
                throw new RuntimeException("Tenant immutability column [{$table}.{$column}] is missing.");
            }

            $trigger = $table.'_tenant_immutable';
            if ($driver === 'pgsql') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON \"{$table}\"");
                DB::statement("CREATE TRIGGER {$trigger} BEFORE UPDATE ON \"{$table}\" FOR EACH ROW WHEN (OLD.{$column} IS DISTINCT FROM NEW.{$column}) EXECUTE FUNCTION baxela_forbid_tenant_id_change()");
            } elseif ($driver === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
                DB::statement("CREATE TRIGGER {$trigger} BEFORE UPDATE ON \"{$table}\" FOR EACH ROW WHEN OLD.{$column} IS NOT NEW.{$column} BEGIN SELECT RAISE(ABORT, 'tenant_id is immutable'); END;");
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        foreach (TenantTableInventory::storeOwned() as $table) {
            $trigger = $table.'_tenant_immutable';
            if ($driver === 'pgsql') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON \"{$table}\"");
            } elseif ($driver === 'sqlite') {
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
    }
};
