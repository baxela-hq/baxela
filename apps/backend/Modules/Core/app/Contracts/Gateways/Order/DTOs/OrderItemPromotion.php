<?php

namespace Modules\Core\Contracts\Gateways\Order\DTOs;

/**
 * The promotion facts an order item must snapshot so its invoice stays
 * self-contained even after the promotion row is hard-deleted:
 * what the unit cost before the promotion, what the promotion actually
 * took off, and which promotion applied. All amounts are major-unit
 * decimal strings; the charged unit price itself travels in the
 * cart-item price_snapshot like always.
 */
final readonly class OrderItemPromotion
{
    public function __construct(
        public int $variant_id,
        public string $base_price,
        public string $promotion_discount,
        public ?int $promotion_id,
    ) {}
}
