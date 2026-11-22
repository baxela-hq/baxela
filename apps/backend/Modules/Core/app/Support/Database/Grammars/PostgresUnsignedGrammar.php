<?php

namespace Modules\Core\Support\Database\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Fluent;

/**
 * The unsigned-preserving schema grammar (postgres-migration DD9/DD9b).
 *
 * Laravel's stock PostgresGrammar has NO `Unsigned` modifier — the flag
 * is silently dropped (verified: PostgresGrammar::$modifiers carries no
 * Unsigned; only MySqlGrammar implements it), so an unsigned column
 * compiles to a plain signed type with no constraint: negatives become
 * storable with no error. This subclass restores the MySQL semantics
 * with two mechanisms, both gated on `$column->unsigned` so signed
 * columns compile stock:
 *
 *  1. Widened storage types where the MySQL range exceeds the PG type:
 *     unsignedTinyInteger → smallint, unsignedSmallInteger → integer,
 *     unsignedInteger → bigint (55 columns widened one step — negligible
 *     storage, full range parity). unsignedBigInteger stays bigint: the
 *     2^64-1 ceiling is ACCEPTED (DD9) — `id()` is bigserial with the
 *     same ceiling on both engines, so values above 2^63 could never
 *     join to an id anyway.
 *  2. A `CHECK ("column" >= 0)` constraint via the modifier hook — the
 *     MySqlGrammar modifier pattern applied to PostgreSQL. Decimal
 *     columns keep their precision-bounded parity via the same check.
 *
 * Installed per-connection through DB::extend('pgsql', ...) in the Core
 * service provider, so purge/reconnect/re-resolution all re-apply it.
 */
class PostgresUnsignedGrammar extends PostgresGrammar
{
    /** @var list<string> the modifier chain WITH the unsigned check */
    protected $modifiers = ['Collate', 'Nullable', 'Default', 'VirtualAs', 'StoredAs', 'GeneratedAs', 'Increment', 'Unsigned'];

    /**
     * The runtime object is always a ColumnDefinition (Fluent subclass
     * carrying the ->unsigned / ->name properties); larastan cannot see
     * the framework's own Fluent magic, hence the local typed alias.
     *
     * @param  \Illuminate\Database\Schema\ColumnDefinition  $column
     */
    /** @param Fluent<array-key, mixed> $column */
    protected function modifyUnsigned(Blueprint $blueprint, Fluent $column): ?string
    {
        $unsigned = (bool) $column->__get('unsigned');

        if (! $unsigned) {
            return null; // Signed columns compile stock.
        }

        return sprintf(' check (%s >= 0)', $this->wrap((string) $column->__get('name')));
    }

    /**
     * Widen only non-auto-incrementing unsigned columns: the serial
     * family already lands one storage size up (smallserial/serial/
     * bigserial), and an auto-increment PK never stores negatives —
     * widening it would silently break serial semantics (the
     * migrations-table regression this guard prevents).
     *
     * @param Fluent<array-key, mixed> $column
     */
    protected function typeSmallInteger(Fluent $column): string
    {
        if ($this->isUnsigned($column) && ! $this->isAutoIncrement($column)) {
            return 'integer';
        }

        return parent::typeSmallInteger($column);
    }

    /** @param Fluent<array-key, mixed> $column */
    protected function typeInteger(Fluent $column): string
    {
        if ($this->isUnsigned($column) && ! $this->isAutoIncrement($column)) {
            return 'bigint';
        }

        return parent::typeInteger($column);
    }

    /** @param Fluent<array-key, mixed> $column */
    protected function typeBigInteger(Fluent $column): string
    {
        // bigint on both paths: the unsigned 2^63+ range is the accepted
        // ceiling (bigserial ids impose it identically) — only the
        // non-negativity check (above) is added.
        return parent::typeBigInteger($column);
    }

    /** Runtime ColumnDefinition accessor larastan can verify. */
    /** @param Fluent<array-key, mixed> $column */
    private function isUnsigned(Fluent $column): bool
    {
        return (bool) $column->__get('unsigned');
    }

    /** @param Fluent<array-key, mixed> $column */
    private function isAutoIncrement(Fluent $column): bool
    {
        return (bool) $column->__get('autoIncrement');
    }
}
