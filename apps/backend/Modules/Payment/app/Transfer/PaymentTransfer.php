<?php

namespace Modules\Payment\Transfer;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;
use Modules\Payment\Schemas\PaymentRefund\PaymentRefundSchema;

/**
 * Payment module's implementation of the data-transfer standard:
 * export-only. Payments and refunds are the money trail of orders —
 * replaying them would forge transactions — so no import surface is
 * exposed. The enabled-methods catalog (PaymentMethod) travels too, as
 * configuration; driver credentials live in config, never here.
 */
class PaymentTransfer implements ModuleTransferInterface
{
    public const string SECTION_PAYMENTS = 'payments';

    public const string SECTION_PAYMENT_METHODS = 'payment-methods';

    public const string SECTION_REFUNDS = 'refunds';

    public function sections(): array
    {
        return [
            TransferSection::exportOnly(self::SECTION_PAYMENT_METHODS),
            TransferSection::exportOnly(self::SECTION_PAYMENTS),
            TransferSection::exportOnly(self::SECTION_REFUNDS),
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

    /**
     * Export-only: the Transfer engine skips these sections before it
     * ever reaches here.
     */
    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        throw new InvalidArgumentException("Section [{$section}] is export-only.");
    }

    /**
     * @param  Builder  $query
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
    private function attributesToPayload($record): array
    {
        $payload = [];
        foreach ($record->getAttributes() as $key => $value) {
            $payload[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->toIso8601String(),
                default => $value,
            };
        }

        return $payload;
    }
}
