<?php

namespace Modules\Tenant\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\Tenancy\TenantTableInventory;

/**
 * Discovers raw query-builder chains and database validation rules in the
 * application source for the per-site tenancy allowlists (multi-tenancy
 * §4.3, rows 48/49/50). Sites are keyed by file, enclosing method and
 * occurrence, so a new chain in an already-registered file is a new site.
 */
final class TenantQueryScanner
{
    /**
     * @return list<array{key: string, table: string, operation: string, guarded: bool, guard: string|null}>
     */
    public static function querySites(): array
    {
        $sites = [];

        foreach (AppCodeScanner::files() as $file) {
            $code = AppCodeScanner::code($file);
            $imports = AppCodeScanner::imports($code);

            preg_match_all('/\bDB::table\(\s*([^,)]+)/', $code, $matches, PREG_OFFSET_CAPTURE);

            $seen = [];
            foreach ($matches[1] as $match) {
                [$expression, $offset] = $match;
                $table = self::tableName(trim($expression), $imports);
                $method = self::enclosingMethod($code, (int) $offset);
                $index = ($seen[$method.'|'.$table] ?? 0) + 1;
                $seen[$method.'|'.$table] = $index;

                // One chain can be several statements long, and a guard may
                // wrap the builder (`TenantQueryScope::apply(DB::table(...))`)
                // or be assigned in the statement before it. Start at the
                // previous statement boundary; stop at the next chain so one
                // site's guard is never credited to its neighbour.
                $before = substr($code, 0, (int) $offset);
                $start = max(
                    strrpos($before, ';') ?: -1,
                    strrpos($before, '{') ?: -1,
                    strrpos($before, '}') ?: -1,
                );
                $statement = substr($code, $start + 1, 1200);
                $currentChain = strpos($statement, 'DB::table(');
                $end = strpos($statement, ';', $currentChain === false ? 0 : $currentChain);
                if ($end !== false) {
                    $statement = substr($statement, 0, $end + 1);
                }

                $operation = 'select';
                foreach (['insertOrIgnore', 'updateOrInsert', 'insertGetId', 'upsert', 'insert', 'update', 'delete'] as $verb) {
                    if (str_contains($statement, '->'.$verb.'(')) {
                        $operation = $verb;

                        break;
                    }
                }

                $guard = null;
                foreach ([
                    'stamp' => '/TenantWriteStamp::|TenantColumn::TENANT_ID|[\'\"]tenant_id[\'\"]/',
                    'scope' => '/TenantQueryScope::apply\(/',
                ] as $kind => $pattern) {
                    if (preg_match($pattern, $statement) === 1) {
                        $guard = $kind;

                        break;
                    }
                }

                // Transfer writes build their stamped `$values` array before
                // entering a closure that performs the actual builder call.
                $writes = ['insert', 'insertOrIgnore', 'insertGetId', 'upsert', 'updateOrInsert', 'update', 'delete'];
                if (
                    $guard === null
                    && in_array($operation, $writes, true)
                    && str_contains(substr($code, max(0, (int) $offset - 6000), 6000), 'TenantWriteStamp::')
                ) {
                    $guard = 'stamp';
                }

                $sites[] = [
                    'key' => $file.'::'.$method.'::'.$table.'#'.$index,
                    'table' => $table,
                    'operation' => $operation,
                    'guarded' => $guard !== null,
                    'guard' => $guard,
                ];
            }
        }

        return $sites;
    }

    /**
     * A site belongs to the allowlist when the table is tenant-owned, is a
     * tenant-less pivot, or could not be statically resolved. Unresolved
     * variables and constants are included deliberately: skipping them would
     * let a new store-owned chain hide behind a dynamic table expression.
     */
    public static function isRelevantTable(string $table): bool
    {
        return TenantTableInventory::isStoreOwned($table)
            || in_array($table, TenantTableInventory::pivots(), true)
            || str_starts_with($table, '$')
            || str_contains($table, '::');
    }

    /**
     * @return list<array{key: string, table: string, kind: string}>
     */
    public static function validationSites(): array
    {
        $sites = [];

        foreach (AppCodeScanner::files() as $file) {
            $code = AppCodeScanner::code($file);
            $imports = AppCodeScanner::imports($code);

            preg_match_all(
                '/Rule::(unique|exists)\(\s*([^,)]+)|[\'\"](unique|exists):([A-Za-z0-9_]+)/',
                $code,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            $seen = [];
            foreach ($matches[0] as $i => $match) {
                [$text, $offset] = $match;
                $kind = $matches[1][$i][0] !== '' ? $matches[1][$i][0] : $matches[3][$i][0];
                $expression = $matches[2][$i][0] !== '' ? $matches[2][$i][0] : $matches[4][$i][0];
                $table = self::tableName(trim($expression), $imports);
                $method = self::enclosingMethod($code, (int) $offset);
                $index = ($seen[$method.'|'.$kind.'|'.$table] ?? 0) + 1;
                $seen[$method.'|'.$kind.'|'.$table] = $index;

                $sites[] = [
                    'key' => $file.'::'.$method.'::'.$kind.':'.$table.'#'.$index,
                    'table' => $table,
                    'kind' => $kind,
                ];
            }
        }

        return $sites;
    }

    /**
     * Resolves the table expression used by a raw chain. Unresolvable
     * variables are kept verbatim: they are potential store-owned sites and
     * must still be registered rather than silently skipped.
     *
     * @param  array<string, string>  $imports
     */
    private static function tableName(string $expression, array $imports): string
    {
        // `Schema::OPERATIONS_TABLE.' AS c'` — the alias never changes the table.
        if (preg_match('/^(.+?)\.\s*[\'\"][^\'\"]+[\'\"]$/', $expression, $aliased) === 1) {
            $expression = trim($aliased[1]);
        }

        if (preg_match('/^[\'\"]([A-Za-z0-9_]+)[\'\"]$/', $expression, $quoted) === 1) {
            return $quoted[1];
        }

        if (preg_match('/^([A-Za-z_]\w*)::([A-Z][A-Z0-9_]*)$/', $expression, $constant) === 1) {
            $class = $imports[$constant[1]] ?? null;
            if ($class !== null && defined($class.'::'.$constant[2])) {
                return (string) constant($class.'::'.$constant[2]);
            }
        }

        // `Role::class` is a model-backed table reference in a validation rule.
        if (preg_match('/^([A-Za-z_]\w*)::class$/', $expression, $model) === 1) {
            $class = $imports[$model[1]] ?? null;
            if ($class !== null && class_exists($class) && is_subclass_of($class, Model::class)) {
                return (new $class)->getTable();
            }
        }

        // String rule form: `unique:table,column` is captured as `table`.
        return trim($expression, '\'"');
    }

    private static function enclosingMethod(string $code, int $offset): string
    {
        $before = substr($code, 0, $offset);
        preg_match_all('/\bfunction\s+&?(\w+)\s*\(/', $before, $functions, PREG_OFFSET_CAPTURE);

        return $functions[1] === [] ? 'file' : end($functions[1])[0];
    }
}
