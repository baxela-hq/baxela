<?php

namespace Modules\Inventory\Gateways;

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
}
