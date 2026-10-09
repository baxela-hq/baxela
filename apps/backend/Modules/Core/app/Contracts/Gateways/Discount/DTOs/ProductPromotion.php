<?php

namespace Modules\Core\Contracts\Gateways\Discount\DTOs;

use Modules\Core\Support\Money;

/**
 * A resolved, currently-active promotion for one product — the winner among
 * all matching promotions, ready to price any of the product's variants.
 *
 * $type is a plain string ('percent'|'fixed') and $value a major-unit
 * decimal string ('15.00' == 15%), matching the coupon value conventions;
 * the contract does not leak the Discount module's enums.
 */
final readonly class ProductPromotion
{
    public function __construct(
        public int $promotion_id,
        public string $type,
        public string $value,
        public ?string $ends_at,
    ) {}

    /**
     * Apply the promotion to one unit's base price. One calculation
     * produces both results so every consumer agrees on the split:
     * percent — base × value/100 rounded half-up once; fixed — the face
     * value clamped at the base price (a $40 promotion on a $30 product
     * discounts $30, leaving a free item, never a negative price).
     */
    public function applyTo(string $baseDecimal): PromotedPrice
    {
        $baseMinor = Money::fromDecimal($baseDecimal);

        $discountMinor = $this->type === 'percent'
            ? Money::applyPercent($baseMinor, $this->value)
            : Money::fromDecimal($this->value);

        $discountMinor = Money::min($discountMinor, $baseMinor);

        return new PromotedPrice(
            effective_minor: $baseMinor - $discountMinor,
            discount_minor: $discountMinor,
        );
    }
}
