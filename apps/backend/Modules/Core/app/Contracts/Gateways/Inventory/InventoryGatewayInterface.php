<?php

namespace Modules\Core\Contracts\Gateways\Inventory;

interface InventoryGatewayInterface
{
    public function checkAvailability(string $variantId, int $quantity): bool;

    /**
     * Stock currently held for a variant; null when the variant has no
     * stock record, which the shop treats as not sellable.
     */
    public function availableQuantity(string $variantId): ?int;

    /**
     * Atomically remove stock for a variant. Returns false when there is
     * not enough quantity left, in which case nothing is changed.
     */
    public function decrement(string $variantId, int $quantity): bool;

    /**
     * Return stock for a variant (order cancelled/expired before payment).
     */
    public function restore(string $variantId, int $quantity): void;
}
