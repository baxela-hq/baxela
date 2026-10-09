<?php

namespace Modules\Core\Contracts\Gateways\Discount\DTOs;

/**
 * The outcome of applying one promotion to one unit's base price — the
 * single authoritative result every price surface (product display, cart
 * snapshot, checkout, order item) is derived from.
 *
 * Invariant: effective_minor + discount_minor == the base price in minor
 * units. The discount is the ACTUAL applied amount (already clamped at the
 * base price), never the promotion's configured value.
 */
final readonly class PromotedPrice
{
    public function __construct(
        public int $effective_minor,
        public int $discount_minor,
    ) {}
}
