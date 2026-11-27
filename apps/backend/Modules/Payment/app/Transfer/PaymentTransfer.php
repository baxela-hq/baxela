<?php

namespace Modules\Payment\Transfer;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Contracts\Gateways\Transfer\TransferStrategyEnum;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantQueryScope;
use Modules\Core\Support\Tenancy\TenantWriteStamp;
use Modules\Core\Support\Transfer\TransferImportTransaction;
use Modules\Core\Support\Transfer\TransferRunContext;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Module;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;
use Throwable;

/**
 * Payment module's implementation of the data-transfer standard (C5):
 * the money trail — payment methods, payments, refunds — imports as
 * INERT records. The importer writes rows through the query builder:
 * no gateway/driver code ever runs, no payment state transitions, no
 * order updates, no events. A successful payment import does NOT make
 * an order paid — paid-ness is the ORDER's own imported state (its
 * payment_status column), never a side effect here.
 *
 * Idempotency: methods upsert by their unique `method` key; payments are
 * insert-only and never mutated — the ledger remap is the primary
 * identity, and a pre-existing local payment may be ADOPTED only via the
 * unambiguous tuple (non-null transaction_id + order + method + amount,
 * exactly one match, F24); refunds match their unique `uuid`.
 */
class PaymentTransfer implements ModuleTransferInterface
{
    public const string SECTION_PAYMENTS = 'payments';

    public const string SECTION_PAYMENT_METHODS = 'payment-methods';

    public const string SECTION_REFUNDS = 'refunds';

    public function __construct(
        protected TransferRemapInterface $remaps,
        protected TransferImportTransaction $units,
    ) {}

    public function sections(): array
    {
        return [
            TransferSection::full(self::SECTION_PAYMENT_METHODS),
            TransferSection::full(self::SECTION_PAYMENTS),
            TransferSection::full(self::SECTION_REFUNDS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_PAYMENT_METHODS => $this->exportRowsOf(PaymentMethod::query()->orderBy(PaymentMethodSchema::ID)),
            self::SECTION_PAYMENTS => $this->exportRowsOf(Payment::query()->orderBy(PaymentSchema::ID)),
            self::SECTION_REFUNDS => $this->exportRowsOf(PaymentRefund::query()->orderBy(PaymentRefundSchema::ID)),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        return match ($section) {
            self::SECTION_PAYMENT_METHODS => $this->importMethods($rows, $options),
            self::SECTION_PAYMENTS => $this->importPayments($rows, $options),
            self::SECTION_REFUNDS => $this->importRefunds($rows, $options),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * Methods are configuration: upsert by the unique driver key. An
     * unknown driver fails the row — mirroring the admin action's
     * registered-driver guard, never silently enabling a driver.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importMethods(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_PAYMENT_METHODS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            $method = trim((string) ($payload[PaymentMethodSchema::METHOD] ?? ''));
            if ($method === '' || ! array_key_exists($method, (array) config('payment.drivers'))) {
                $result->recordFailed($label.': unknown_driver:'.$method);

                continue;
            }

            try {
                $existing = PaymentMethod::query()
                    ->where(PaymentMethodSchema::METHOD, $method)
                    ->first();

                if ($options->dryRun) {
                    $this->dryOutcome($result, $existing !== null, $options);

                    continue;
                }

                if ($existing !== null && $options->strategy === TransferStrategyEnum::SKIP) {
                    $result->recordSkipped();

                    continue;
                }

                $values = [
                    PaymentMethodSchema::METHOD => $method,
                    PaymentMethodSchema::IS_ACTIVE => (bool) ($payload[PaymentMethodSchema::IS_ACTIVE] ?? false),
                    PaymentMethodSchema::SORT_ORDER => (int) ($payload[PaymentMethodSchema::SORT_ORDER] ?? 0),
                    TenantColumn::TENANT_ID => TenantWriteStamp::tenantIdForWrite(),
                ];

                // Write + remap record share the ownership-verified unit.
                $this->units->run(function (TransferRunContext $context) use ($values, $existing, $sourceId): void {
                    if ($existing !== null) {
                        $existing->update($values);
                        $localId = (int) $existing->getKey();
                    } else {
                        $localId = (int) DB::table(PaymentMethodSchema::TABLE)->insertGetId($values);
                    }

                    $this->remaps->record($context->sourceInstallation, Module::NAME_LOWER, 'payment-methods', (int) $sourceId, $localId);
                });

                if ($existing !== null) {
                    $result->recordUpdated();
                } else {
                    $result->recordCreated();
                }
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Payments are insert-only history. Order refs resolve remap-first
     * (raw for identified files); identity is the ledger remap, with
     * adoption of a pre-existing payment only via the unambiguous
     * transaction tuple (F24) — never first-match.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importPayments(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_PAYMENTS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;
            $sourceId = $row[self::KEY_SOURCE_ID] ?? null;

            if (! is_array($payload) || ! is_numeric($sourceId)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            try {
                // Already imported in an earlier run: skip, never mutate.
                $remappedId = $this->remaps->resolve($options->sourceInstallation, Module::NAME_LOWER, 'payments', (int) $sourceId);
                if ($remappedId !== null) {
                    if ($this->paymentExists($remappedId)) {
                        $result->recordSkipped();

                        continue;
                    }

                    $remappedId = null; // dangling: fall through to import
                }

                $localOrderId = $this->resolveOrderId($payload[PaymentSchema::ORDER_ID] ?? null, $options);
                if ($localOrderId === null) {
                    $result->recordFailed($label.': unresolved_order:'.($payload[PaymentSchema::ORDER_ID] ?? '?'));

                    continue;
                }

                $transactionId = $payload[PaymentSchema::TRANSACTION_ID] ?? null;

                // F24 adoption: only via the full unambiguous tuple with a
                // non-null transaction id, and only EXACTLY ONE match.
                $adoptable = null;
                if ($transactionId !== null && $transactionId !== '') {
                    $matches = TenantQueryScope::apply(DB::table(PaymentSchema::TABLE)
                        ->where(PaymentSchema::ORDER_ID, $localOrderId)
                        ->where(PaymentSchema::METHOD, $payload[PaymentSchema::METHOD] ?? '')
                        ->where(PaymentSchema::AMOUNT, (string) ($payload[PaymentSchema::AMOUNT] ?? ''))
                        ->where(PaymentSchema::TRANSACTION_ID, $transactionId))
                        ->pluck('id');

                    if ($matches->count() > 1) {
                        $result->recordFailed($label.': ambiguous_payment_match');

                        continue;
                    }

                    $adoptable = $matches->count() === 1 ? (int) $matches->first() : null;
                }

                if ($options->dryRun) {
                    $this->dryOutcome($result, $adoptable !== null, $options);

                    continue;
                }

                $values = $payload;
                unset($values['id']);
                $values[PaymentSchema::ORDER_ID] = $localOrderId;
                $values[TenantColumn::TENANT_ID] = TenantWriteStamp::tenantIdForWrite();

                // Write + remap record share the ownership-verified unit.
                $this->units->run(function (TransferRunContext $context) use ($values, $adoptable, $sourceId): void {
                    $localId = $adoptable ?? (int) DB::table(PaymentSchema::TABLE)->insertGetId($values);

                    $this->remaps->record($context->sourceInstallation, Module::NAME_LOWER, 'payments', (int) $sourceId, $localId);
                });

                if ($adoptable !== null) {
                    $result->recordSkipped();
                    $result->warn($label.': adopted_existing_payment:'.$adoptable);
                } else {
                    $result->recordCreated();
                }
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Refunds match their unique uuid (the natural key) — immutable like
     * the payments they settle. payment/order refs resolve through the
     * ledger; the admin-attributor nulls out when unresolvable.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function importRefunds(array $rows, ImportOptions $options): ImportResult
    {
        $result = ImportResult::empty();

        foreach ($rows as $index => $row) {
            $label = self::SECTION_REFUNDS.'['.($index + 1).']';
            $payload = $row[self::KEY_PAYLOAD] ?? null;

            if (! is_array($payload)) {
                $result->recordFailed($label.': invalid_row');

                continue;
            }

            try {
                $uuid = trim((string) ($payload[PaymentRefundSchema::UUID] ?? ''));
                if ($uuid === '') {
                    $result->recordFailed($label.': missing_uuid');

                    continue;
                }

                $existingId = TenantQueryScope::apply(DB::table(PaymentRefundSchema::TABLE)
                    ->where(PaymentRefundSchema::UUID, $uuid))
                    ->value('id');

                if ($options->dryRun) {
                    $this->dryOutcome($result, $existingId !== null, $options);

                    continue;
                }

                if ($existingId !== null) {
                    if ($options->strategy === TransferStrategyEnum::SKIP) {
                        $result->recordSkipped();

                        continue;
                    }

                    // Immutable history: a matched refund is satisfied —
                    // nothing to update, count it as such.
                    $result->recordUpdated();

                    continue;
                }

                $localPaymentId = $this->resolveRemappableRef(
                    $payload[PaymentRefundSchema::PAYMENT_ID] ?? null,
                    'payments',
                    $options,
                    fn (int $id): bool => $this->paymentExists($id),
                );
                if ($localPaymentId === null) {
                    $result->recordFailed($label.': unresolved_payment:'.($payload[PaymentRefundSchema::PAYMENT_ID] ?? '?'));

                    continue;
                }

                $localOrderId = $this->resolveOrderId($payload[PaymentRefundSchema::ORDER_ID] ?? null, $options);
                if ($localOrderId === null) {
                    $result->recordFailed($label.': unresolved_order:'.($payload[PaymentRefundSchema::ORDER_ID] ?? '?'));

                    continue;
                }

                $values = $payload;
                unset($values['id']);
                $values[PaymentRefundSchema::PAYMENT_ID] = $localPaymentId;
                $values[PaymentRefundSchema::ORDER_ID] = $localOrderId;
                $values[TenantColumn::TENANT_ID] = TenantWriteStamp::tenantIdForWrite();

                // The admin attributor is a nullable loose ref (auth domain).
                $refundedBy = $payload[PaymentRefundSchema::REFUNDED_BY] ?? null;
                if ($refundedBy !== null) {
                    $resolvedAdmin = $this->remaps->resolve($options->sourceInstallation, 'user', 'users', (int) $refundedBy);
                    $values[PaymentRefundSchema::REFUNDED_BY] = ($resolvedAdmin !== null && DB::table('auth_users')->where('id', $resolvedAdmin)->exists())
                        ? $resolvedAdmin
                        : null;
                    if ($values[PaymentRefundSchema::REFUNDED_BY] === null) {
                        $result->warn($label.': loose_ref_unresolved:user.users:'.$refundedBy);
                    }
                }

                // Write + remap record share the ownership-verified unit.
                $this->units->run(function (TransferRunContext $context) use ($values, $row): void {
                    $localId = (int) DB::table(PaymentRefundSchema::TABLE)->insertGetId($values);

                    $sourceId = (int) ($row[self::KEY_SOURCE_ID] ?? 0);
                    if ($sourceId > 0) {
                        $this->remaps->record($context->sourceInstallation, Module::NAME_LOWER, 'refunds', $sourceId, $localId);
                    }
                });

                $result->recordCreated();
            } catch (Throwable $e) {
                TransferLockSessionLostException::rethrow($e); // session loss is fatal (C7), never a row failure
                report($e);
                $result->recordFailed($label.': '.($e->getMessage() !== '' ? $e->getMessage() : class_basename($e)));
            }
        }

        return $result;
    }

    /**
     * Order reference: remap tier → raw tier (identified/allowed) → null.
     * Non-nullable callers treat null as a row failure.
     */
    private function resolveOrderId(mixed $sourceOrderId, ImportOptions $options): ?int
    {
        if (! is_numeric($sourceOrderId)) {
            return null;
        }

        $remapped = $this->remaps->resolve($options->sourceInstallation, 'order', 'orders', (int) $sourceOrderId);
        if ($remapped !== null && $this->orderExists($remapped)) {
            return $remapped;
        }

        $rawAllowed = $options->identified || $options->allowRawIds;
        if ($rawAllowed && $this->orderExists((int) $sourceOrderId)) {
            return (int) $sourceOrderId;
        }

        return null;
    }

    /**
     * @param  (callable(int): bool)|null  $exists
     */
    private function resolveRemappableRef(mixed $sourceRef, string $entity, ImportOptions $options, ?callable $exists): ?int
    {
        if (! is_numeric($sourceRef)) {
            return null;
        }

        $remapped = $this->remaps->resolve($options->sourceInstallation, Module::NAME_LOWER, $entity, (int) $sourceRef);
        if ($remapped !== null && ($exists === null || $exists($remapped))) {
            return $remapped;
        }

        return null;
    }

    private function orderExists(int $id): bool
    {
        return TenantQueryScope::apply(DB::table('order_orders')->where('id', $id))->exists();
    }

    private function paymentExists(int $id): bool
    {
        return TenantQueryScope::apply(DB::table(PaymentSchema::TABLE)->where('id', $id))->exists();
    }

    /** Dry runs only ever create (nothing exists in an empty preview). */
    private function dryOutcome(ImportResult $result, bool $matched, ImportOptions $options): void
    {
        if ($matched && $options->strategy === TransferStrategyEnum::SKIP) {
            $result->recordSkipped();
        } elseif ($matched) {
            $result->recordUpdated();
        } else {
            $result->recordCreated();
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportRowsOf($query): array
    {
        $rows = [];
        $query->chunkById(200, function ($records) use (&$rows): void {
            foreach ($records as $record) {
                $rows[] = [
                    self::KEY_SOURCE_ID => (int) $record->getKey(),
                    self::KEY_PAYLOAD => $this->attributesToPayload($record),
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
     * @param  Model  $record
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
