<?php

namespace Modules\Cart\Transfer;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;

/**
 * Cart module's implementation of the data-transfer standard:
 * export-only. Carts are ephemeral storefront state tied to tokens and
 * users of the exporting store; the price snapshots travel for
 * reporting, never for replay.
 */
class CartTransfer implements ModuleTransferInterface
{
    public const string SECTION_CARTS = 'carts';

    public const string SECTION_CART_ITEMS = 'cart-items';

    public function sections(): array
    {
        return [
            TransferSection::exportOnly(self::SECTION_CARTS),
            TransferSection::exportOnly(self::SECTION_CART_ITEMS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_CARTS => $this->exportRowsOf(Cart::query()->orderBy(CartSchema::ID)),
            // Some cart-item columns (labels, slugs, images) are filled by
            // runtime queries, not mass assignment — the attribute export
            // reads them all.
            self::SECTION_CART_ITEMS => $this->exportRowsOf(CartItem::query()->orderBy(CartItemSchema::ID)),
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
                    self::KEY_PAYLOAD => $record->getAttributes(),
                ];
            }
        });

        return $rows;
    }
}
