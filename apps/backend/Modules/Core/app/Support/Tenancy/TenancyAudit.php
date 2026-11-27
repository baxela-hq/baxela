<?php

namespace Modules\Core\Support\Tenancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;
use Modules\Core\Schemas\Shared\PrimaryKeyColumn;
use Modules\Core\Schemas\Shared\TenantColumn;

/**
 * Layer-4 integrity check (multi-tenancy §4.3): reports rows with no owner,
 * references to an unknown tenant, and relations whose two ends belong to
 * different stores. Direct relations are discovered from database foreign
 * keys; the nine tenant-less pivots come from `core.tenancy.audit_pivots`.
 *
 * This is detective, not preventive: external SQL is outside the threat
 * model, but a planted cross-tenant relation must always be visible here.
 */
final class TenancyAudit
{
    public function __construct(private readonly TenantGatewayInterface $tenants) {}

    /**
     * @return array{violations: list<array{type: string, table: string, detail: string}>, relations: int}
     */
    public function run(): array
    {
        /** @var list<array{type: string, table: string, detail: string}> $violations */
        $violations = [];
        $relations = 0;
        $tenantIds = $this->tenants->allIds();
        $column = TenantColumn::TENANT_ID;
        $id = PrimaryKeyColumn::ID;

        foreach (TenantTableInventory::storeOwned() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if (! in_array($table, TenantTableInventory::platformTier(), true)) {
                $null = DB::table($table)->whereNull($column)->first([$id]);
                if ($null !== null) {
                    $violations[] = [
                        'type' => 'null_tenant',
                        'table' => $table,
                        'detail' => 'row '.(string) $null->{$id}.' has no tenant_id',
                    ];
                }
            }

            $orphan = DB::table($table)
                ->whereNotNull($column)
                ->whereNotIn($column, $tenantIds)
                ->first([$id, $column]);
            if ($orphan !== null) {
                $violations[] = [
                    'type' => 'unknown_tenant',
                    'table' => $table,
                    'detail' => 'row '.(string) $orphan->{$id}.' references unknown tenant '
                        .(string) $orphan->{$column},
                ];
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];
                if (! TenantTableInventory::isStoreOwned($parent) || ! Schema::hasColumn($parent, $column)) {
                    continue;
                }

                $pairs = [];
                foreach ($foreignKey['columns'] as $index => $childColumn) {
                    $parentColumn = $foreignKey['foreign_columns'][$index] ?? null;
                    if ($parentColumn === null) {
                        continue 2;
                    }
                    $pairs[$childColumn] = $parentColumn;
                }
                unset($pairs[$column]);
                if (count($pairs) !== 1) {
                    continue;
                }

                /** @var string $childColumn */
                $childColumn = array_key_first($pairs);
                /** @var string $parentColumn */
                $parentColumn = $pairs[$childColumn];
                $relations++;

                $mismatch = DB::table($table.' as child')
                    ->join($parent.' as parent', 'child.'.$childColumn, '=', 'parent.'.$parentColumn)
                    ->whereColumn('child.'.$column, '<>', 'parent.'.$column)
                    ->select([
                        'child.'.$id.' as child_id',
                        'parent.'.$id.' as parent_id',
                        'child.'.$column.' as child_tenant',
                        'parent.'.$column.' as parent_tenant',
                    ])
                    ->first();

                if ($mismatch !== null) {
                    $violations[] = [
                        'type' => 'tenant_mismatch',
                        'table' => $table,
                        'detail' => sprintf(
                            '%s:%s (tenant %s) references %s:%s (tenant %s)',
                            $table,
                            (string) $mismatch->child_id,
                            (string) $mismatch->child_tenant,
                            $parent,
                            (string) $mismatch->parent_id,
                            (string) $mismatch->parent_tenant,
                        ),
                    ];
                }
            }
        }

        $pivots = config('core.tenancy.audit_pivots', []);
        if (is_array($pivots)) {
            foreach ($pivots as $relation) {
                if (! is_array($relation) || ! isset($relation['pivot'], $relation['left'])) {
                    continue;
                }

                /** @var array{table: string, column: string} $left */
                $left = $relation['left'];
                $pivot = (string) $relation['pivot'];
                if (! Schema::hasTable($pivot) || ! Schema::hasTable($left['table'])) {
                    continue;
                }

                $relations++;
                $right = $relation['right'] ?? null;

                if (! is_array($right)) {
                    // Installation-level right side (countries): only the
                    // store-owned parent can carry an owner.
                    $orphan = DB::table($pivot)
                        ->leftJoin($left['table'].' as owner', $pivot.'.'.$left['column'], '=', 'owner.'.$id)
                        ->whereNull('owner.'.$id)
                        ->first([$pivot.'.'.$left['column'].' as orphan_id']);
                    if ($orphan !== null) {
                        $violations[] = [
                            'type' => 'orphan_pivot',
                            'table' => $pivot,
                            'detail' => 'row references missing '.$left['table'].':'.(string) $orphan->orphan_id,
                        ];
                    }

                    continue;
                }

                /** @var array{table: string, column: string} $right */
                if (! Schema::hasTable($right['table'])) {
                    continue;
                }

                $mismatch = DB::table($pivot)
                    ->join($left['table'].' as left_end', $pivot.'.'.$left['column'], '=', 'left_end.'.$id)
                    ->join($right['table'].' as right_end', $pivot.'.'.$right['column'], '=', 'right_end.'.$id)
                    ->whereColumn('left_end.'.$column, '<>', 'right_end.'.$column)
                    ->select([
                        'left_end.'.$id.' as left_id',
                        'right_end.'.$id.' as right_id',
                        'left_end.'.$column.' as left_tenant',
                        'right_end.'.$column.' as right_tenant',
                    ])
                    ->first();

                if ($mismatch !== null) {
                    $violations[] = [
                        'type' => 'tenant_mismatch',
                        'table' => $pivot,
                        'detail' => sprintf(
                            'pivot %s joins %s:%s (tenant %s) to %s:%s (tenant %s)',
                            $pivot,
                            $left['table'],
                            (string) $mismatch->left_id,
                            (string) $mismatch->left_tenant,
                            $right['table'],
                            (string) $mismatch->right_id,
                            (string) $mismatch->right_tenant,
                        ),
                    ];
                }
            }
        }

        return ['violations' => $violations, 'relations' => $relations];
    }
}
