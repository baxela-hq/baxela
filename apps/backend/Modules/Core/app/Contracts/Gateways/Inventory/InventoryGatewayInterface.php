<?php

namespace Modules\Core\Contracts\Gateways\Inventory;

interface InventoryGatewayInterface
{
    public function checkAvailability(string $variantId, int $quantity): bool;
}
