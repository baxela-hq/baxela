<?php

namespace Modules\Order\Transfer;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderAddress;
use Modules\Order\Models\OrderItem;
use Modules\Order\Models\OrderReturn;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;

/**
 * Order module's implementation of the data-transfer standard:
 * export-only. Orders (with their items, addresses and returns) are
 * transactional history — replaying them would forge revenue records,
 * stock movements and payment trails, so no import surface is exposed.
 * Items/addresses/returns ship as separate sections ordered after their
 * orders; every payload column is exported faithfully (enums as values,
 * datetimes as ISO-8601).
 */
class OrderTransfer implements ModuleTransferInterface
{
    public const string SECTION_ORDERS = 'orders';

    public const string SECTION_ORDER_ITEMS = 'order-items';

    public const string SECTION_ORDER_ADDRESSES = 'order-addresses';

    public const string SECTION_ORDER_RETURNS = 'order-returns';

    public function sections(): array
    {
        return [
            TransferSection::exportOnly(self::SECTION_ORDERS),
            TransferSection::exportOnly(self::SECTION_ORDER_ITEMS),
            TransferSection::exportOnly(self::SECTION_ORDER_ADDRESSES),
            TransferSection::exportOnly(self::SECTION_ORDER_RETURNS),
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
