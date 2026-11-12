<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Modules\Auth\Models\SignInLockout;
use Modules\Auth\Schemas\SignInLockout\SignInLockoutSchema;
use Modules\Auth\Support\SignInLockout\SignInLockoutService;
use Modules\Auth\Tests\Feature\HelperTrait;

/**
 * F25 — concurrent first-row bootstrap. Lives in its own file because
 * RefreshDatabase wraps every test in a transaction: connection 1's
 * commit would only release a savepoint and the row would never truly
 * commit. DatabaseMigrations (migrate:fresh, no wrapper) makes real
 * cross-connection commits observable. MySQL-gated — SQLite has no row
 * locks. Run this file in its own pest invocation.
 */
uses(DatabaseMigrations::class);
uses(HelperTrait::class);

it('bootstraps the same unseen lockout row concurrently from two connections', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('F25 requires MySQL row locks — run with DB_CONNECTION=mysql.');
    }

    config([
        'database.connections.auth_lockout_second' => config('database.connections.'.config('database.default')),
    ]);
    $second = DB::connection('auth_lockout_second');

    // Reviewer check 1: a genuinely separate PDO, not connection 1's.
    expect($second->getPdo())->not->toBe(DB::connection()->getPdo());

    $service = app(SignInLockoutService::class);
    $emailHash = SignInLockoutService::emailKey('never-seen@example.com');

    // Connection 1 bootstraps the unseen row and stays uncommitted.
    DB::connection()->beginTransaction();
    $service->bootstrapForUpdate($emailHash, DB::connection());

    // Connection 2 must contend on the uncommitted unique-key insert — a
    // bounded lock wait proves the serialization (reviewer check 2 part 1).
    $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
    try {
        $second->beginTransaction();
        $service->bootstrapForUpdate($emailHash, $second);
        $this->fail('Expected connection 2 to block on the uncommitted bootstrap.');
    } catch (QueryException) {
        $second->rollBack();
    }

    // Connection 1 commits FOR REAL (DatabaseMigrations — no wrapper).
    DB::connection()->commit();

    // Connection 2 completes on the same single row (reviewer check 2 part 2).
    $second->beginTransaction();
    $row = $service->bootstrapForUpdate($emailHash, $second);
    $second->commit();

    expect($row->{SignInLockoutSchema::EMAIL_HASH})->toBe($emailHash)
        ->and(SignInLockout::query()->where(SignInLockoutSchema::EMAIL_HASH, $emailHash)->count())->toBe(1);
});
