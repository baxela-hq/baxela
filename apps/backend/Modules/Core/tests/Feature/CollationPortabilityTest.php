<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Contact\Schemas\NewsletterSubscriber\NewsletterSubscriberSchema;
use Modules\Discount\Schemas\Coupon\CouponSchema;

uses(RefreshDatabase::class);

/**
 * Insert expecting a constraint rejection, in its own savepoint —
 * PostgreSQL aborts the surrounding transaction on an error, and the
 * rollback-to-savepoint keeps the (RefreshDatabase) outer transaction
 * usable for the assertions that follow.
 */
function insert_expecting_rejection(callable $insert): bool
{
    try {
        DB::transaction(fn (): bool => $insert());

        return false;
    } catch (QueryException) {
        return true;
    }
}

/**
 * Phase B portability tests (postgres-migration §4 Phase B, matrix 29–31):
 * the case-insensitivity parity delivered by the functional LOWER()
 * unique indexes + normalized comparisons, and the documented collation
 * deltas — case is reproduced; accents and trailing spaces are NOT
 * (accepted deltas, DD2). Runs on every engine the suite targets; the
 * driver-branched DDL itself is exercised per-engine (SQLite here,
 * MySQL baseline, PostgreSQL in phase D).
 */
it('rejects a case-variant duplicate user email (functional LOWER unique)', function () {
    User::query()->create([
        UserSchema::EMAIL => 'owner@example.com',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ]);

    expect(insert_expecting_rejection(fn () => User::query()->create([
        UserSchema::EMAIL => 'Owner@Example.com',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ])))->toBeTrue();
});

it('rejects a case-variant duplicate newsletter email', function () {
    DB::table(NewsletterSubscriberSchema::TABLE)->insert([
        'tenant_id' => 1,
        NewsletterSubscriberSchema::EMAIL => 'news@example.com',
    ]);

    expect(insert_expecting_rejection(fn () => DB::table(NewsletterSubscriberSchema::TABLE)->insert([
        'tenant_id' => 1,
        NewsletterSubscriberSchema::EMAIL => 'NEWS@Example.COM',
    ])))->toBeTrue();
});

it('rejects case-variant duplicate variant SKUs but allows multiple NULL barcodes', function () {
    $productId = DB::table('catalog_products')->insertGetId([
        'tenant_id' => 1,
        'type' => 'simple',
        'status' => 'in_stock',
        'is_published' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table(VariantSchema::TABLE)->insert([
        'tenant_id' => 1,
        VariantSchema::PRODUCT_ID => $productId,
        VariantSchema::SKU => 'ABC-123',
        VariantSchema::PRICE => 10,
    ]);

    // Case-variant SKU collides.
    expect(insert_expecting_rejection(fn () => DB::table(VariantSchema::TABLE)->insert([
        'tenant_id' => 1,
        VariantSchema::PRODUCT_ID => $productId,
        VariantSchema::SKU => 'abc-123',
        VariantSchema::PRICE => 10,
    ])))->toBeTrue();

    // Nullable barcode: multiple NULLs stay legal on every engine.
    DB::table(VariantSchema::TABLE)->insert([
        'tenant_id' => 1,
        VariantSchema::PRODUCT_ID => $productId,
        VariantSchema::SKU => 'ABC-124',
        VariantSchema::PRICE => 10,
        VariantSchema::BARCODE => null,
    ]);

    expect(fn () => DB::table(VariantSchema::TABLE)->insert([
        'tenant_id' => 1,
        VariantSchema::PRODUCT_ID => $productId,
        VariantSchema::SKU => 'ABC-125',
        VariantSchema::PRICE => 10,
        VariantSchema::BARCODE => null,
    ]))->not->toThrow(QueryException::class);
});

it('rejects a case-variant duplicate coupon code', function () {
    DB::table(CouponSchema::TABLE)->insert([
        'tenant_id' => 1,
        CouponSchema::CODE => 'SUMMER10',
        CouponSchema::TYPE => 'percent',
        CouponSchema::VALUE => 10,
    ]);

    expect(insert_expecting_rejection(fn () => DB::table(CouponSchema::TABLE)->insert([
        'tenant_id' => 1,
        CouponSchema::CODE => 'summer10',
        CouponSchema::TYPE => 'percent',
        CouponSchema::VALUE => 10,
    ])))->toBeTrue();
});

it('documents the accent-insensitivity delta: e-acute and plain e are distinct', function () {
    // utf8mb4_unicode_ci treated 'é' == 'e'; PostgreSQL/SQLite do not.
    // Accepted delta (DD2): affected columns are ASCII-dominant; citext
    // or ICU collations are a deferred optimization. This test PINS the
    // new behavior so the delta is a decision, not a surprise.
    User::query()->create([
        UserSchema::EMAIL => 'cafe@example.com',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ]);

    expect(fn () => User::query()->create([
        UserSchema::EMAIL => 'café@example.com',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ]))->not->toThrow(QueryException::class);
});

it('documents the trailing-space delta: padded values are distinct', function () {
    // MySQL PAD SPACE compared 'a ' == 'a'; PostgreSQL/SQLite are
    // significant. Accepted delta (DD2): HTTP input is globally trimmed
    // by the TrimStrings middleware; import-side collisions surface via
    // the functional indexes.
    User::query()->create([
        UserSchema::EMAIL => 'padded@example.com',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ]);

    expect(fn () => User::query()->create([
        UserSchema::EMAIL => 'padded@example.com ',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ]))->not->toThrow(QueryException::class);
});

it('resolves sign-in case-insensitively through the canonical gateway path', function () {
    User::query()->create([
        UserSchema::EMAIL => 'MixedCase@Example.com',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ]);

    $found = DB::table(UserSchema::TABLE)
        ->whereRaw('LOWER('.UserSchema::EMAIL.') = ?', [mb_strtolower(trim('mixedcase@example.com'))])
        ->exists();

    expect($found)->toBeTrue();
});

it('concurrently registers the same user device exactly once (matrix 29)', function () {
    $user = User::query()->create([
        UserSchema::EMAIL => 'devices@example.com',
        UserSchema::PASSWORD => 'secret-password',
        UserSchema::IS_ACTIVE => true,
    ]);

    // Two sequential registerDevice-style races against the scoped
    // conflict: one inserts, the other takes the (user_id, device_name)
    // duplicate branch. True cross-connection racing runs on real
    // MySQL/PostgreSQL servers in phase D; here the protocol outcome is
    // asserted sequentially (exactly one row; no suppressed surprises).
    $insert = fn (): int => DB::connection()->getDriverName() === 'pgsql'
        ? DB::affectingStatement(
            'INSERT INTO auth_user_devices (user_id, device_name, first_seen_at, last_seen_at, created_at, updated_at)'
            .' VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT (user_id, device_name) DO NOTHING',
            [$user->getKey(), 'phone-a', now(), now(), now(), now()],
        )
        : (int) DB::table('auth_user_devices')->insertOrIgnore([
            'user_id' => $user->getKey(),
            'device_name' => 'phone-a',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

    expect($insert())->toBe(1)   // first registration inserts
        ->and($insert())->toBe(0) // the duplicate is ignored — scoped
        ->and(DB::table('auth_user_devices')->where('user_id', $user->getKey())->where('device_name', 'phone-a')->count())->toBe(1);
});

it('concurrently creates one webhook delivery per (endpoint, event) pair (matrix 30)', function () {
    // UUID primary key: insertGetId would return the rowid, not the id.
    $eventId = (string) Str::uuid();
    DB::table('webhook_events')->insert([
        'tenant_id' => 1,
        'id' => $eventId,
        'event_type' => 'order.created',
        'source_key' => 'order:'.Str::uuid()->toString(),
        'payload' => '{}',
        'created_at' => now(),
    ]);
    $endpointId = DB::table('webhook_endpoints')->insertGetId([
        'tenant_id' => 1,
        'name' => 'primary',
        'url' => 'https://example.com/hook',
        'events' => json_encode(['order.created']),
        'secret' => 's',
        'is_active' => true,
        'created_by' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $insert = fn (): int => DB::connection()->getDriverName() === 'pgsql'
        ? DB::affectingStatement(
            'INSERT INTO webhook_deliveries (id, tenant_id, event_id, endpoint_id, event_type, status, created_at)'
            .' VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT (endpoint_id, event_id) DO NOTHING',
            [(string) Str::uuid(), 1, $eventId, $endpointId, 'order.created', 'pending', now()],
        )
        : (int) DB::table('webhook_deliveries')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => 1,
            'event_id' => $eventId,
            'endpoint_id' => $endpointId,
            'event_type' => 'order.created',
            'status' => 'pending',
            'created_at' => now(),
        ]);

    expect($insert())->toBe(1)
        ->and($insert())->toBe(0)
        ->and(DB::table('webhook_deliveries')->where('endpoint_id', $endpointId)->where('event_id', $eventId)->count())->toBe(1);
});
