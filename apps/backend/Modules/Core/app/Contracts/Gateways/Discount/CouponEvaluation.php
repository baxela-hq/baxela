<?php

namespace Modules\Core\Contracts\Gateways\Discount;

use Modules\Core\Contracts\Gateways\Discount\DTOs\CouponDiscountResult;

/**
 * Outcome of an advisory coupon evaluation: either an eligible discount or
 * exactly one failure reason — never both, never neither.
 */
class CouponEvaluation
{
    private function __construct(
        public readonly ?CouponDiscountResult $discount,
        public readonly ?CouponFailureEnum $failure,
    ) {}

    public static function eligible(CouponDiscountResult $discount): self
    {
        return new self($discount, null);
    }

    public static function failed(CouponFailureEnum $failure): self
    {
        return new self(null, $failure);
    }

    public function isEligible(): bool
    {
        return ! is_null($this->discount);
    }
}
