<?php

namespace Modules\Order\Transfer;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Discount\DiscountGatewayInterface;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Support\Transfer\TransferImportTransaction;
use Modules\Core\Support\Transfer\TransferRunContext;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\Module;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;
use Throwable;

/**
 * Order module's implementation of the data-transfer standard (C5): the
 * order books — orders, items, addresses, returns — are immutable
 * transactional HISTORY. The importer is bespoke hydration, not
 * business-action replay: rows are written through the query builder
 * (no Eloquent events, no checkout path), with every exported column
 * restored faithfully — statuses, money snapshots and timestamps
 * included. No stock moves, no payment state changes, no coupon capacity
 * is consumed, and the order_code's random generator is bypassed (the
 * source code is kept, the unique index is the backstop).
 *
 * Cross-module references resolve through the C2 tiers: users via the
 * persisted remap (the User directory import recorded them; order
 * payloads carry no email), item variants via remap/raw with
 * fail-the-row semantics (order_items.user_id-like NOT NULL identity),
 * coupon ids via the coupon_code natural key, nullable loose refs
 * (currency, shipping method, tax classes, promotion) null-out with a
 * warning when unresolvable.
 *
 * Atomicity is per (parent order × section) — honest about the runner's
 * section-level dispatch (a transaction cannot span sections): the
 * orders section hydrates parents row-by-row, each child section then
 * replaces one parent's children atomically (explicit delete + insert +
 * remap maintenance, F23/F27), and children of failed/skipped parents
 * never import (`parent_skipped`). A absent section is a no-op; an
 * empty section is authoritative emptiness (stale children removed).
 */
class OrderTransfer implements ModuleTransferInterface
{
    public const string SECTION_ORDERS = 'orders';

    public const string SECTION_ORDER_ITEMS = 'order-items';

    public const string SECTION_ORDER_ADDRESSES = 'order-addresses';

    public const string SECTION_ORDER_RETURNS = 'order-returns';

    /**
     * Source order id → this run's parent state: the local id, whether
     * the parent hydrated successfully, and whether it was subtree-skipped.
     *
     * @var array<int, array{local: int, ok: bool, skipped: bool}>
     */
    private array $orderStateBySource = [];

    public function __construct(
        protected TransferRemapInterface $remaps,
        protected DiscountGatewayInterface $discountGateway,
        protected TransferImportTransaction $units,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_ORDERS),
            TransferSection::full(self::SECTION_ORDER_ITEMS),
            TransferSection::full(self::SECTION_ORDER_ADDRESSES),
            TransferSection::full(self::SECTION_ORDER_RETURNS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_ORDERS => $this->exportRowsOf(Order::query()->orderBy(OrderSchema::ID)),
            // Items and addresses ride along with their orders.
            self::SECTION_ORDER_ITEMS => $this->exportRowsOf(
                OrderItem::query()->orderBy(OrderItemSchema::ID),
                fn ($record) => [...$this->attributesToPayload($record),
                    OrderItemSchema::ORDER_ID => (int) $record->{OrderItemSchema::ORDER_ID}],
            ),
            self::SECTION_ORDER_ADDRESSES => $this->exportRowsOf(
                OrderAddress::query()->orderBy(OrderAddressSchema::ID),
                fn ($record) => [...$this->attributesToPayload($record),
                    OrderAddressSchema::ORDER_ID => (int) $record->{OrderAddressSchema::ORDER_ID}],
            ),
            self::SECTION_ORDER_RETURNS => $this->exportRowsOf(
                OrderReturn::query()->orderBy(OrderReturnSchema::ID),
                fn ($record) => [...$this->attributesToPayload($record),
                    OrderReturnSchema::ORDER_ID => (int) $record->{OrderReturnSchema::ORDER_ID}],
            ),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_ORDERS => $this->importOrders($rows, $options),
            self::SECTION_ORDER_ITEMS => $this->importChildren($rows, $options, self::SECTION_ORDER_ITEMS),
            self::SECTION_ORDER_ADDRESSES => $this->importChildren($rows, $options, self::SECTION_ORDER_ADDRESSES),
            self::SECTION_ORDER_RETURNS => $this->importChildren($rows, $options, self::SECTION_ORDER_RETURNS),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * Parent hydration: match by order_code (the portable natural key),
     * UPDATE re-hydrates the row, SKIP skips the whole subtree, failures
     * isolate per row — and every outcome is tracked so child sections
     * know which parents they may touch.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importOrders(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_ORDERS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $this->orderStateBySource[(int) ($sourceId ?? -1)] = ['local' => 0, 'ok' => false, 'skipped' => false];
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            try {
                $orderCode = trim((string) ($payload[OrderSchema::ORDER_CODE] ?? ''));
                if ($orderCode === '') {
                    $this->orderStateBySource[(int) $sourceId] = ['local' => 0, 'ok' => false, 'skipped' => false];
                    $result->recordFailed($label.': missing_order_code');

                    continue;
                }

                $existing = Order::query()
                    ->where(OrderSchema::ORDER_CODE, $orderCode)
                    ->first();

                if ($existing !== null && $options->strategy === TransferStrategyEnum::SKIP) {
                    // Subtree SKIP: children of this order must not import.
                    $this->orderStateBySource[(int) $sourceId] = ['local' => (int) $existing->getKey(), 'ok' => false, 'skipped' => true];
                    $result->recordSkipped();

                    continue;
                }

                // The identity references (user) resolve before the write;
                // order payload carries no email — remap tier or fail.
                $userId = $this->remaps->resolve($options->sourceInstallation, 'user', 'users', (int) ($payload[OrderSchema::USER_ID] ?? 0));
                if ($userId !== null && ! $this->userExists($userId)) {
                    $userId = null; // dangling mapping: fall through
                }

                $rawAllowed = $options->identified || $options->allowRawIds;
                if ($userId === null) {
                    $rawUserId = (int) ($payload[OrderSchema::USER_ID] ?? 0);
                    if ($rawAllowed && $this->userExists($rawUserId)) {
                        $userId = $rawUserId;
                        $result->warn($label.': raw_id_fallback:user.users:'.$rawUserId);
                    }
                }

                if ($userId === null) {
                    $this->orderStateBySource[(int) $sourceId] = ['local' => 0, 'ok' => false, 'skipped' => false];
                    $result->recordFailed($label.': unresolved_user:'.($payload[OrderSchema::USER_ID] ?? '?'));

                    continue;
                }

                // Hydration row: every exported column, refs resolved.
                $values = $payload;
                unset($values['id']);
                $values[OrderSchema::USER_ID] = $userId;
                $this->resolveNullableRef($values, OrderSchema::CURRENCY_ID, 'core', 'currencies', $options, $result, $label);
                $this->resolveCouponRef($values, $options, $result, $label);
                $this->resolveNullableRef($values, OrderSchema::SHIPPING_METHOD_ID, 'shipping', 'methods', $options, $result, $label);
                $this->resolveNullableRef($values, OrderSchema::SHIPPING_TAX_CLASS_ID, 'tax', 'tax-classes', $options, $result, $label);

                // Write + remap record share the ownership-verified unit.
                $localId = 0;
                $this->units->run(function (TransferRunContext $context) use ($values, $existing, $sourceId, &$localId): void {
                    if ($existing !== null) {
                        // UPDATE re-hydrates the parent row in place; the
                        // child sections replace its children.
                        DB::table(OrderSchema::TABLE)
                            ->where('id', $existing->getKey())
                            ->update($this->timestampsPreserved($values));
                        $localId = (int) $existing->getKey();
                    } else {
                        $localId = (int) DB::table(OrderSchema::TABLE)->insertGetId($values);
                    }

                    $this->remaps->record($context->sourceInstallation, Module::NAME_LOWER, 'orders', (int) $sourceId, $localId);
                });

                $this->orderStateBySource[(int) $sourceId] = ['local' => $localId, 'ok' => true, 'skipped' => false];

                if ($existing !== null) {
                    $result->recordUpdated();
                } else {
                    $result->recordCreated();
                }
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                $this->orderStateBySource[(int) $sourceId] = ['local' => 0, 'ok' => false, 'skipped' => false];
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * One child section: per (parent order × section) atomic replace —
     * explicit delete of the order's existing rows (FK cascades fire on
     * parent delete, not child replace), insert of the incoming rows
     * (zero is valid = authoritative emptiness, F27), remap maintenance
     * in the same transaction (F23). Children of skipped/failed parents
     * never import.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importChildren(array $rows, ImportOptions $options, string $section): ImportResult
    {
        $result = ImportResult::empty();
        [$table, $orderKey, $refResolution] = match ($section) {
            self::SECTION_ORDER_ITEMS => [
                OrderItemSchema::TABLE,
                OrderItemSchema::ORDER_ID,
                fn (array &$values, ImportOptions $o, ImportResult $r, string $l): bool => $this->resolveItemRefs($values, $o, $r, $l),
            ],
            self::SECTION_ORDER_ADDRESSES => [OrderAddressSchema::TABLE, OrderAddressSchema::ORDER_ID, null],
            self::SECTION_ORDER_RETURNS => [OrderReturnSchema::TABLE, OrderReturnSchema::ORDER_ID, null],
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };

        // Parents from THIS run, grouped: only successfully hydrated (or
        // previously known) parents are eligible for child replacement.
        $eligible = array_filter(
            $this->orderStateBySource,
            fn (array $state): bool => $state['ok'],
        );

        $rowsByParent = [];
        foreach ($rows as $index => $row) {
            $label = $section.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $sourceOrderId = (int) ($payload[$orderKey] ?? 0);

            if (! isset($this->orderStateBySource[$sourceOrderId])) {
                // Parent is not from this run at all (partial file, or a
                // remap from an earlier import).
                $remapped = $this->remaps->resolve($options->sourceInstallation, Module::NAME_LOWER, 'orders', $sourceOrderId);
                if ($remapped !== null && $this->orderExists($remapped)) {
                    $rowsByParent[$remapped][] = ['row' => $row, 'label' => $label, 'sourceId' => (int) $sourceId, 'values' => null];
                } else {
                    $result->recordFailed($label.': unresolved_order:'.$sourceOrderId);
                }

                continue;
            }

            $state = $this->orderStateBySource[$sourceOrderId];

            if ($state['skipped']) {
                // Subtree SKIP (C5): a skipped parent never gains children.
                $result->recordSkipped();
                $result->warn($label.': parent_skipped');

                continue;
            }

            if (! $state['ok']) {
                // The parent row failed; history cannot hang in the air.
                $result->recordFailed($label.': parent_failed');
                $result->warn($label.': parent_failed');

                continue;
            }

            $rowsByParent[$state['local']][] = ['row' => $row, 'label' => $label, 'sourceId' => (int) $sourceId, 'values' => null];
        }

        // F27: EVERY eligible parent goes through the replace — a parent
        // whose incoming collection is empty still gets its authoritative
        // delete-all. Parents only known from earlier imports (remap hit,
        // not part of this run's orders section) keep their children.
        $parentsToReplace = array_keys($rowsByParent);
        foreach (array_keys($eligible) as $sourceOrderId) {
            $localOrderId = $eligible[$sourceOrderId]['local'];
            if (! in_array($localOrderId, $parentsToReplace, true)) {
                $rowsByParent[$localOrderId] = [];
            }
        }

        foreach ($rowsByParent as $localOrderId => $group) {
            // Validate + resolve ALL rows of the group first (C5/F27:
            // group-level all-or-nothing — one malformed row freezes the
            // whole replace at its previous state).
            $prepared = [];
            $groupFailed = false;
            foreach ($group as $entry) {
                $values = $entry['row'][self::KEY_PAYLOAD];
                unset($values['id']);
                $values[$orderKey] = $localOrderId;

                if ($refResolution !== null && ! $refResolution($values, $options, $result, $entry['label'])) {
                    $groupFailed = true;

                    break;
                }

                $prepared[] = ['values' => $values, 'sourceId' => $entry['sourceId'], 'label' => $entry['label']];
            }

            if ($groupFailed) {
                // Valid siblings freeze with the bad row: the (order ×
                // section) unit stays all-or-nothing (C5).
                foreach ($prepared as $entry) {
                    $result->recordSkipped();
                    $result->warn($entry['label'].': group_validation_failed');
                }

                continue;
            }

            try {
                $this->units->run(function (TransferRunContext $context) use ($table, $orderKey, $localOrderId, $prepared): void {
                    // F27: even an EMPTY prepared set is authoritative —
                    // delete the order's existing children, insert none.
                    $staleLocalIds = DB::table($table)
                        ->where($orderKey, $localOrderId)
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    // F23: the deleted children's mappings die with them.
                    $this->remaps->forgetByLocalIds(
                        $context->sourceInstallation,
                        Module::NAME_LOWER,
                        $this->entityForTable($table),
                        $staleLocalIds,
                    );

                    DB::table($table)->where($orderKey, $localOrderId)->delete();

                    foreach ($prepared as $entry) {
                        $childId = (int) DB::table($table)->insertGetId($entry['values']);
                        $this->remaps->record(
                            $context->sourceInstallation,
                            Module::NAME_LOWER,
                            $this->entityForTable($table),
                            $entry['sourceId'],
                            $childId,
                        );
                    }
                });

                foreach ($prepared as $entry) {
                    $result->recordCreated();
                }
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);

                foreach ($prepared as $entry) {
                    $result->recordFailed($entry['label'].': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
                }
            }
        }

        return $result;
    }

    /**
     * Order items: variant identity is NOT NULL (fail the row), the
     * promotion ref is a nullable loose ref, and the historical price
     * invariant is checked with decimal-string arithmetic as a WARNING —
     * history hydrates faithfully even when it violates today's rules.
     */
    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveItemRefs(array &$values, ImportOptions $options, ImportResult $result, string $label): bool
    {
        $variantId = $this->remaps->resolve($options->sourceInstallation, 'catalog', 'variants', (int) ($values[OrderItemSchema::VARIANT_ID] ?? 0));
        if ($variantId !== null && ! $this->variantExists($variantId)) {
            $variantId = null; // dangling
        }

        $rawAllowed = $options->identified || $options->allowRawIds;
        if ($variantId === null) {
            $rawVariant = (int) ($values[OrderItemSchema::VARIANT_ID] ?? 0);
            if ($rawAllowed && $this->variantExists($rawVariant)) {
                $variantId = $rawVariant;
                $result->warn($label.': raw_id_fallback:catalog.variants:'.$rawVariant);
            }
        }

        if ($variantId === null) {
            $result->recordFailed($label.': unresolved_variant:'.($values[OrderItemSchema::VARIANT_ID] ?? '?'));

            return false;
        }

        $values[OrderItemSchema::VARIANT_ID] = $variantId;
        $this->resolveNullableRef($values, OrderItemSchema::TAX_CLASS_ID, 'tax', 'tax-classes', $options, $result, $label);
        $this->resolveNullableRef($values, OrderItemSchema::PROMOTION_ID, 'discount', 'promotions', $options, $result, $label);

        // Historical invariant as a warning (never reject a real record).
        $base = $values[OrderItemSchema::BASE_PRICE] ?? null;
        $promo = $values[OrderItemSchema::PROMOTION_DISCOUNT] ?? null;
        $snapshot = $values[OrderItemSchema::PRICE_SNAPSHOT] ?? null;
        if ($base !== null && $snapshot !== null) {
            // Exact decimal arithmetic on integer minor units parsed from
            // strings — no floats, no bcmath dependency (F10).
            $expected = $this->decimalToMinor((string) $base) - $this->decimalToMinor((string) ($promo ?? '0'));
            if ($expected !== $this->decimalToMinor((string) $snapshot)) {
                $result->warn($label.': price_invariant_mismatch');
            }
        }

        return true;
    }

    /**
     * The coupon reference prefers the portable natural key (coupon_code)
     * over the source id; nullable — unresolved history nulls out.
     *
     * @param  array<string, mixed>  $values
     */
    private function resolveCouponRef(array &$values, ImportOptions $options, ImportResult $result, string $label): void
    {
        $couponCode = trim((string) ($values[OrderSchema::COUPON_CODE] ?? ''));
        $resolved = null;

        if ($couponCode !== '') {
            $resolved = $this->discountGateway->couponIdByCode($couponCode);
        }

        if ($resolved === null) {
            // Tier 1/3 via the generic remap/raw path on the source id.
            $this->resolveNullableRef($values, OrderSchema::COUPON_ID, 'discount', 'coupons', $options, $result, $label);

            return;
        }

        $sourceCouponId = isset($values[OrderSchema::COUPON_ID]) ? (int) $values[OrderSchema::COUPON_ID] : 0;
        if ($sourceCouponId > 0 && $sourceCouponId !== $resolved) {
            $result->warn($label.': natural_key_overrides_remap:discount.coupons:'.$sourceCouponId);
        }

        $values[OrderSchema::COUPON_ID] = $resolved;
    }

    /**
     * Nullable loose reference (C2 tier 1→3→4-null): remap, then raw id
     * when the file's identity allows, then null with a warning.
     *
     * @param  array<string, mixed>  $values
     */
    private function resolveNullableRef(array &$values, string $column, string $module, string $entity, ImportOptions $options, ImportResult $result, string $label): void
    {
        $sourceRef = $values[$column] ?? null;

        if ($sourceRef === null) {
            return;
        }

        $remapped = $this->remaps->resolve($options->sourceInstallation, $module, $entity, (int) $sourceRef);
        if ($remapped !== null && $this->refExists($module, $entity, $remapped)) {
            $values[$column] = $remapped;

            return;
        }

        $rawAllowed = $options->identified || $options->allowRawIds;
        if ($rawAllowed && $this->refExists($module, $entity, (int) $sourceRef)) {
            $values[$column] = (int) $sourceRef;
            $result->warn($label.": raw_id_fallback:{$module}.{$entity}:{$sourceRef}");

            return;
        }

        $values[$column] = null;
        $result->warn($label.": loose_ref_unresolved:{$module}.{$entity}:{$sourceRef}");
    }

    /** Liveness probes through the owning modules' tables — read-only. */
    private function refExists(string $module, string $entity, int $id): bool
    {
        return match ([$module, $entity]) {
            ['core', 'currencies'] => DB::table('core_currencies')->where('id', $id)->exists(),
            ['shipping', 'methods'] => DB::table('shipping_methods')->where('id', $id)->exists(),
            ['tax', 'tax-classes'] => DB::table('tax_classes')->where('id', $id)->exists(),
            ['discount', 'coupons'] => DB::table('discount_coupons')->where('id', $id)->exists(),
            ['discount', 'promotions'] => DB::table('discount_promotions')->where('id', $id)->exists(),
            ['catalog', 'variants'] => DB::table('catalog_variants')->where('id', $id)->exists(),
            default => false,
        };
    }

    private function userExists(int $id): bool
    {
        return DB::table('auth_users')->where('id', $id)->exists();
    }

    private function variantExists(int $id): bool
    {
        return DB::table('catalog_variants')->where('id', $id)->exists();
    }

    private function orderExists(int $id): bool
    {
        return DB::table(OrderSchema::TABLE)->where('id', $id)->exists();
    }

    private function entityForTable(string $table): string
    {
        return match ($table) {
            OrderItemSchema::TABLE => 'order-items',
            OrderAddressSchema::TABLE => 'order-addresses',
            OrderReturnSchema::TABLE => 'order-returns',
            default => 'orders',
        };
    }

    /**
     * Exact decimal-string → integer minor units (truncating past 2dp).
     * No floats: money comparisons never go through binary arithmetic.
     */
    private function decimalToMinor(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        [$int, $frac] = array_pad(explode('.', $value, 2), 2, '0');
        $frac = substr($frac.'00', 0, 2);

        $minor = ((int) $int) * 100 + (int) $frac;

        return $negative ? -$minor : $minor;
    }

    /**
     * UPDATE keeps payload timestamps from overwriting the row's
     * updated_at column with a stale source value — drop updated_at from
     * an update (created_at is preserved inside $values).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function timestampsPreserved(array $values): array
    {
        unset($values['updated_at']);

        return $values;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportRowsOf($query, ?\Closure $payload = null): array
    {
        $rows = [];
        $query->chunkById(200, function ($records) use (&$rows, $payload): void {
            foreach ($records as $record) {
                $rows[] = [
                    self::KEY_SOURCE_ID => (int) $record->getKey(),
                    self::KEY_PAYLOAD => $payload
                        ? $payload($record)
                        : $this->attributesToPayload($record),
                ];
            }
        });

        return $rows;
    }

    /**
     * Every column as a portable scalar: enums as values, datetimes as
     * ISO-8601 — a faithful export, not an admin-API echo.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Model  $record
     * @return array<string, mixed>
     */
    private function attributesToPayload($record): array
    {
        $payload = [];
        foreach ($record->getAttributes() as $key => $value) {
            $payload[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                default => $value,
            };
        }

        return $payload;
    }
}
