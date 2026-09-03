<?php

namespace Modules\Inventory\Gateways;

use Modules\Core\Contracts\Events\Inventory\StockDecreasedEvent;
use Modules\Core\Contracts\Events\Inventory\StockDepletedEvent;
use Modules\Core\Contracts\Events\Inventory\StockIncreasedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
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
}
