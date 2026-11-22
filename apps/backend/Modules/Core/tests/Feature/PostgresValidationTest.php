<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Schemas\TransferLock\TransferLockSchema;
use Modules\Core\Support\Transfer\TransferClock;

uses(RefreshDatabase::class);

/**
 * PostgreSQL-only validation (postgres-migration phase D, matrix 32/39):
 * the identifier audit over pg_constraint + pg_indexes, and the clock
 * regression a non-PG engine cannot express — a transaction that starts
 * before lease expiry must not see the expired lease as live when it
 * evaluates after (the clock_timestamp vs now() distinction).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL-only validation — runs on the pg CI leg and the dev container.');
    }
});

it('keeps every constraint and index name within 63 bytes without truncation collisions', function () {
    $names = DB::table('pg_constraint')->pluck('conname')
        ->merge(DB::table('pg_indexes')->pluck('indexname'))
        ->unique()
        ->values();

    $tooLong = $names->filter(fn ($name) => strlen((string) $name) > 63);

    expect($tooLong->all())->toBe([], "Names over PostgreSQL's 63-byte NAMEDATALEN:\n".$tooLong->implode("\n"));

    // Truncation collisions: two distinct names must not share a 63-byte
    // prefix (PG would silently alias them).
    $truncated = [];
    $collisions = [];
    foreach ($names as $name) {
        $key = substr((string) $name, 0, 63);
        if (isset($truncated[$key]) && $truncated[$key] !== $name) {
            $collisions[] = $truncated[$key].' ~ '.$name;
        }
        $truncated[$key] = (string) $name;
    }

    expect($collisions)->toBe([], "Truncation collisions at 63 bytes:\n".implode("\n", $collisions));
    expect($names)->not->toBeEmpty();
});

it('rejects a lease that expired while the verifying transaction was open (clock regression, matrix 39)', function () {
    $connection = DB::connection();
    $now = $connection->raw(TransferClock::now($connection));

    // A lease with a 1-second lifetime.
    $connection->table(TransferLockSchema::TABLE)->insert([
        TransferLockSchema::NAME => 'transfer:import:clock-test',
        TransferLockSchema::OWNER_TOKEN => '11111111-2222-3333-4444-555555555555',
        TransferLockSchema::GENERATION => 1,
        TransferLockSchema::HEARTBEAT_AT => $now,
        TransferLockSchema::EXPIRES_AT => $connection->raw(TransferClock::nowPlus($connection, 1)),
    ]);

    $leaseLive = null;

    // One transaction: opens BEFORE expiry, evaluates AFTER it. With the
    // transaction-pinned now() this would wrongly see the lease as live;
    // clock_timestamp() must not.
    $connection->transaction(function () use ($connection, &$leaseLive): void {
        $row = $connection->selectOne(sprintf(
            'SELECT CASE WHEN %1$s > %2$s THEN 1 ELSE 0 END AS lease_live FROM %3$s WHERE %4$s = ?',
            TransferLockSchema::EXPIRES_AT,
            TransferClock::now($connection),
            TransferLockSchema::TABLE,
            TransferLockSchema::NAME,
        ), ['transfer:import:clock-test']);

        $leaseLiveBeforeSleep = (int) $row->lease_live;

        $connection->statement("SELECT pg_sleep(1.5)");
        $row = $connection->selectOne(sprintf(
            'SELECT CASE WHEN %1$s > %2$s THEN 1 ELSE 0 END AS lease_live FROM %3$s WHERE %4$s = ?',
            TransferLockSchema::EXPIRES_AT,
            TransferClock::now($connection),
            TransferLockSchema::TABLE,
            TransferLockSchema::NAME,
        ), ['transfer:import:clock-test']);

        // The first read may be live (pre-expiry); the post-sleep read,
        // inside the SAME transaction, must see the expiry.
        $leaseLive = [$leaseLiveBeforeSleep, (int) $row->lease_live];
    });

    expect($leaseLive[1])->toBe(0, 'A lease that expired mid-transaction must not read as live — clock_timestamp() vs now()');
});

it('enforces the unsigned CHECK constraints on live writes', function () {
    $productId = DB::table('catalog_products')->insertGetId([
        'type' => 'simple', 'status' => 'in_stock', 'is_published' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Negative money: rejected by the CHECK the grammar added. Each
    // expected failure runs in its own savepoint — PostgreSQL aborts the
    // whole transaction on an error, and the rollback-to-savepoint keeps
    // the outer (RefreshDatabase) transaction usable for what follows.
    $insertVariant = function (string $sku, int|float $price, int $quantity) use ($productId): bool {
        try {
            DB::transaction(fn () => DB::table('catalog_variants')->insert([
                'product_id' => $productId, 'sku' => $sku, 'price' => $price, 'quantity' => $quantity,
            ]));

            return true;
        } catch (Illuminate\Database\QueryException) {
            return false; // Savepoint rolled back; the outer transaction stays usable.
        }
    };

    expect($insertVariant('NEG-1', -5, 1))->toBeFalse()
        ->and($insertVariant('NEG-2', 5, -1))->toBeFalse();

    // MySQL-max values fit the widened types (range parity): quantity is
    // unsignedSmallInteger — its 65535 ceiling fits the widened integer.
    expect(fn () => DB::table('catalog_variants')->insert([
        'product_id' => $productId, 'sku' => 'BIG-QTY', 'price' => 5, 'quantity' => 65535,
    ]))->not->toThrow(Illuminate\Database\QueryException::class);
});

it('keeps the grammar applied across reconnects (DD9b lifecycle, matrix 35)', function () {
    // Purge destroys the connection instance; the next use re-resolves it
    // through the pgsql extension, which must re-apply the grammar.
    DB::purge(config('database.default'));
    Schema::hasTable('users');

    expect(get_class(DB::connection()->getSchemaGrammar()))->toBe(
        Modules\Core\Support\Database\Grammars\PostgresUnsignedGrammar::class,
    );
});
