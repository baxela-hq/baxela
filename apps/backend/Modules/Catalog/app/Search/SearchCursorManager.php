<?php

namespace Modules\Catalog\Search;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use RuntimeException;

/**
 * The per-product journal cursor protocol (postgres-migration §3.4.1):
 * an explicit, lock-serialized ordering authority that — unlike a global
 * bigserial allocation — provably reflects COMMIT order.
 *
 * Every journal mutation for a product runs, inside ONE transaction with
 * the journal insert: `SELECT … FOR UPDATE` on the product's cursor row
 * (initializing it savepoint-safely on first mutation), the insert, and
 * `cursor = cursor + 1` while holding the lock. The lock is released only
 * at COMMIT/ROLLBACK, so cursor values are assigned in commit order and
 * an aborted increment is never visible.
 *
 * The runtime guard (`assertAppendable`) enforces the global lock-ordering
 * invariant (§3.4.1b): an append for a product whose cursor lock was not
 * acquired in the CURRENT transaction fails closed — hidden mutation
 * paths (listeners, observers, nested helpers) cannot journal without
 * having fenced first. The lock bookkeeping is transaction-scoped,
 * connection-specific, and savepoint-aware (snapshot-restore semantics):
 * a `ROLLBACK TO SAVEPOINT` restores the pre-savepoint lock set exactly,
 * matching the locks the server actually retains.
 */
final class SearchCursorManager
{

    /**
     * The authoritative per-product cursor (the journal's commit-ordered
     * version). Read-only view for reconcilers and diagnostics.
     */
    public function cursorFor(int $productId): ?int
    {
        $value = DB::table(SearchCoordinationSchema::CURSORS_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->value(SearchCoordinationSchema::CURSOR);

        return $value === null ? null : (int) $value;
    }

    /**
     * Acquire the product's cursor row FOR UPDATE (§3.4.1a initialization:
     * savepoint-safe INSERT under concurrent first mutations — the loser
     * of the insert race blocks on the winner's uncommitted row until it
     * resolves, so first mutations serialize exactly like later ones).
     *
     * MUST run inside the caller's transaction, as the first lock it
     * takes. Idempotent within a transaction: re-locking a product the
     * transaction already holds is a no-op.
     *
     * @throws CursorRetiredException when the product ID was retired
     *                                (pruned) — permanent, by ID
     * @throws RuntimeException when called outside a transaction
     */
    public function lockForMutation(int $productId): int
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('search_cursor_lock_requires_transaction');
        }

        // Retired check under the same serialization boundary: retirement
        // commits only while holding the cursor-row lock and deleting the
        // row, so an absent row + registry hit = provably post-retirement.
        if ($this->isRetired($productId)) {
            throw new CursorRetiredException($productId);
        }

        $row = $this->lockRow($productId);

        if ($row === null) {
            try {
                // Nested transaction = SAVEPOINT: a concurrent first
                // mutation's unique violation rolls back only this
                // savepoint, leaving the caller's transaction usable.
                DB::transaction(function () use ($productId): void {
                    DB::table(SearchCoordinationSchema::CURSORS_TABLE)->insert([
                        SearchCoordinationSchema::PRODUCT_ID => $productId,
                        SearchCoordinationSchema::CURSOR => 0,
                    ]);
                });
            } catch (QueryException) {
                // Lost the first-mutation race — fall through and block on
                // the winner's row (it resolves at their commit/rollback).
            }

            $row = $this->lockRow($productId);

            if ($row === null) {
                // insert failed without a conflict AND the row is absent —
                // surface rather than guess.
                throw new RuntimeException('search_cursor_initialization_failed');
            }
        }

        self::recordLock($productId, (int) $row->{SearchCoordinationSchema::CURSOR});

        return (int) $row->{SearchCoordinationSchema::CURSOR};
    }

    /**
     * Advance the cursor under the held lock — the write side of the same
     * transaction that locked and journaled. Also stamps the operation row
     * (the durable record of the cursor value each mutation represents).
     *
     * @throws RuntimeException when the row vanished under us (must not
     *                          happen under the FOR UPDATE discipline)
     */
    public function advance(int $productId, int $operationId): void
    {
        $this->assertAppendable($productId);

        $advanced = DB::table(SearchCoordinationSchema::CURSORS_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->update([
                SearchCoordinationSchema::CURSOR => DB::raw(SearchCoordinationSchema::CURSOR.' + 1'),
                SearchCoordinationSchema::LAST_OPERATION_ID => $operationId,
            ]);

        if ($advanced !== 1) {
            throw new RuntimeException('search_cursor_row_missing');
        }
    }

    /**
     * The runtime guard (§3.4.1b): the current transaction must hold the
     * product's cursor lock. Fail closed on hidden mutation paths.
     */
    public function assertAppendable(int $productId): void
    {
        if (! self::transactionHoldsLock($productId)) {
            throw new RuntimeException('search_cursor_lock_not_held');
        }
    }

    public function isRetired(int $productId): bool
    {
        return DB::table(SearchCoordinationSchema::RETIRED_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->exists();
    }

    /**
     * Append one journal mutation under the cursor protocol: lock →
     * supersede-and-insert (the existing {@see SearchOutbox} semantics) →
     * advance. MUST run inside the caller's business transaction; opens
     * none. Returns the new operation id.
     *
     * This is the sanctioned journal-append seam — every mutation path
     * goes through here (see the mutation-paths registry).
     */
    public function append(int $productId): int
    {
        $this->lockForMutation($productId);

        $operationId = app(SearchOutbox::class)->record($productId);

        $this->advance($productId, $operationId);

        return $operationId;
    }

    /** @return object|null the locked row, or null when absent */
    private function lockRow(int $productId): ?object
    {
        return DB::table(SearchCoordinationSchema::CURSORS_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->lockForUpdate()
            ->first();
    }

    // -----------------------------------------------------------------------
    // Transaction-scoped, connection-specific, savepoint-aware bookkeeping.
    // Mirrors the connection's own transaction stack: BEGIN pushes an empty
    // frame, COMMIT merges it down, ROLLBACK (incl. savepoint rollback)
    // discards it — exactly the locks the server retains (C2/C4).
    // -----------------------------------------------------------------------

    /** @var array<string, array<int, array<int, true>>> connection name → [level => {productId: true}] */
    private static array $held = [];

    private static function stateKey(): string
    {
        return DB::connection()->getName();
    }

    private static function recordLock(int $productId, int $cursor): void
    {
        $key = self::stateKey();
        $level = max(1, DB::transactionLevel());

        self::$held[$key][$level][$productId] = true;
        self::pruneAbove($key, $level);
    }

    private static function transactionHoldsLock(int $productId): bool
    {
        $key = self::stateKey();
        $level = DB::transactionLevel();

        if ($level === 0 || empty(self::$held[$key])) {
            return false;
        }

        self::pruneAbove($key, $level);

        for ($i = 1; $i <= $level; $i++) {
            if (isset(self::$held[$key][$i][$productId])) {
                return true;
            }
        }

        return false;
    }

    /** Drop frames above the live transaction level (savepoint rollback). */
    private static function pruneAbove(string $key, int $level): void
    {
        foreach (array_keys(self::$held[$key] ?? []) as $frame) {
            if ($frame > $level) {
                unset(self::$held[$key][$frame]);
            }
        }

        if (self::$held[$key] === []) {
            unset(self::$held[$key]);
        }
    }

    /**
     * @internal called by the transaction-event listeners (Catalog service
     * provider): commit merges the top frame into the one below; a full
     * commit (level 0) clears the connection's bookkeeping entirely.
     */
    public static function commitFrame(): void
    {
        $key = self::stateKey();
        $level = DB::transactionLevel();

        if ($level <= 0 || empty(self::$held[$key])) {
            unset(self::$held[$key]); // Outer commit: nothing survives.

            return;
        }

        // Savepoint release: merge the just-committed frame downward.
        $merged = array_replace(
            self::$held[$key][$level] ?? [],
            self::$held[$key][$level - 1] ?? [],
        );

        if ($level - 1 > 0) {
            self::$held[$key][$level - 1] = $merged;
        }

        unset(self::$held[$key][$level]);
    }

    /**
     * @internal rollback discards the top frame (savepoint rollback) or
     * clears everything (outer rollback) — snapshot-restore semantics.
     */
    public static function rollbackFrame(): void
    {
        $key = self::stateKey();
        $level = DB::transactionLevel();

        if ($level <= 0) {
            unset(self::$held[$key]); // Outer rollback: clear all.

            return;
        }

        unset(self::$held[$key][$level]); // Savepoint rollback: drop frame.
    }
}
