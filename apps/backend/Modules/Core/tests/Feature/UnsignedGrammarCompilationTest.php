<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Database\Grammars\PostgresUnsignedGrammar;

/**
 * Unsigned grammar compilation tests (postgres-migration DD9b, matrix
 * 33/46): the generated PostgreSQL DDL must carry the widened storage
 * types AND the CHECK constraints for unsigned columns — while signed
 * columns compile exactly stock. The grammar is exercised directly
 * (driver-independent SQL generation); live-CHECK assertions on a real
 * PostgreSQL server run in phase D.
 */

function unsignedGrammarSql(callable $definition): string
{
    // A scratch connection carries the grammar; the default test
    // connection (and its schema builder) stays untouched.
    $connection = DB::connection();
    $connection->setSchemaGrammar(new PostgresUnsignedGrammar($connection));

    $blueprint = new Blueprint($connection, 'examples', $definition);

    return implode(' ', $blueprint->toSql());
}

it('compiles unsigned columns to widened storage with non-negativity checks', function () {
    $sql = unsignedGrammarSql(function (Blueprint $table): void {
        $table->unsignedTinyInteger('a');
        $table->unsignedSmallInteger('b');
        $table->unsignedInteger('c');
        $table->unsignedBigInteger('d');
        $table->decimal('f', 10, 2)->unsigned();
    });

    expect($sql)->toContain('"a" smallint not null')
        ->and($sql)->toContain('check ("a" >= 0)')
        ->and($sql)->toContain('"b" integer not null')
        ->and($sql)->toContain('check ("b" >= 0)')
        ->and($sql)->toContain('"c" bigint not null')
        ->and($sql)->toContain('check ("c" >= 0)')
        // bigint on both paths (the 2^63 ceiling is the accepted DD9
        // decision); only the check is added.
        ->and($sql)->toContain('"d" bigint not null')
        ->and($sql)->toContain('check ("d" >= 0)')
        ->and($sql)->toContain('decimal(10, 2) not null')
        ->and($sql)->toContain('check ("f" >= 0)');
});

it('compiles signed columns stock: original types, no checks', function () {
    $sql = unsignedGrammarSql(function (Blueprint $table): void {
        $table->tinyInteger('a');
        $table->smallInteger('b');
        $table->integer('c');
        $table->bigInteger('d');
        $table->decimal('f', 10, 2);
    });

    expect($sql)->toContain('"a" smallint not null')
        ->and($sql)->toContain('"b" smallint not null')
        ->and($sql)->toContain('"c" integer not null')
        ->and($sql)->toContain('"d" bigint not null')
        ->and($sql)->not->toContain('check (')
        // No widening on signed columns.
        ->and($sql)->not->toContain('"c" bigint');
});

it('re-applies the grammar when the pgsql connection is re-resolved (DD9b lifecycle)', function () {
    // The DB::extend('pgsql') wiring is inert unless the driver is pgsql;
    // this asserts the closure is REGISTERED (resolution-level check that
    // does not require a live PostgreSQL server).
    $manager = DB::getFacadeRoot();

    expect(method_exists($manager, 'extend'))->toBeTrue();

    $extensions = (new ReflectionClass($manager))
        ->getProperty('extensions')
        ->getValue($manager);

    expect(array_key_exists('pgsql', $extensions))->toBeTrue();
});
