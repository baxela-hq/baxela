<?php

namespace Modules\Inventory\Gateways;

use Modules\Core\Contracts\Events\Inventory\StockDecreasedEvent;
use Modules\Core\Contracts\Events\Inventory\StockDepletedEvent;
use Modules\Core\Contracts\Events\Inventory\StockIncreasedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Catalog\Models\Variant;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

class InventoryGateway implements InventoryGatewayInterface
{
    public function checkAvailability(string $variantId, int $quantity): bool
    {
        return InventoryStock::query()->where([
            [InventoryStockSchema::VARIANT_ID, '=', $variantId],
            [InventoryStockSchema::QUANTITY, '>=', $quantity],
        ])->exists();
    }

    public function availableQuantity(string $variantId): ?int
    {
        $quantity = InventoryStock::query()
            ->where(InventoryStockSchema::VARIANT_ID, $variantId)
            ->value(InventoryStockSchema::QUANTITY);

        return is_null($quantity) ? null : (int) $quantity;
    }

    public function decrement(string $variantId, int $quantity): bool
    {
        $decremented = InventoryStock::query()->where([
            [InventoryStockSchema::VARIANT_ID, '=', $variantId],
            [InventoryStockSchema::QUANTITY, '>=', $quantity],
        ])->decrement(InventoryStockSchema::QUANTITY, $quantity);

        if ($decremented === 0) {
            return false;
        }

        event(StockDecreasedEvent::fill([
            'variant_id' => (int) $variantId,
            'quantity' => $quantity,
        ]));

        $remaining = (int) InventoryStock::query()
            ->where(InventoryStockSchema::VARIANT_ID, $variantId)
            ->value(InventoryStockSchema::QUANTITY);

        if ($remaining === 0) {
            event(StockDepletedEvent::fill([
                'variant_id' => (int) $variantId,
            ]));
        }

        return true;
    }

    public function restore(string $variantId, int $quantity): void
    {
        InventoryStock::query()
            ->where(InventoryStockSchema::VARIANT_ID, $variantId)
            ->increment(InventoryStockSchema::QUANTITY, $quantity);

        event(StockIncreasedEvent::fill([
            'variant_id' => (int) $variantId,
            'quantity' => $quantity,
        ]));
    }

    public function upsertStock(int $variantId, int $quantity): void
    {
        InventoryStock::query()->updateOrCreate(
            [InventoryStockSchema::VARIANT_ID => $variantId],
            [InventoryStockSchema::QUANTITY => $quantity],
        );
    }

    public function pruneOrphanedStocks(): void
    {
        InventoryStock::query()
            ->whereNotIn(InventoryStockSchema::VARIANT_ID, Variant::query()->select(VariantSchema::ID))
            ->delete();
    }
}
