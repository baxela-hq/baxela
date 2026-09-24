<?php

namespace Modules\Core\Contracts\Gateways\Discount\DTOs;

class CouponDiscountResult
{
    public int $coupon_id;

    public string $code;

    /**
     * CouponTypeEnum value ('percent'|'fixed') — kept as a plain string so
     * the contract does not leak the Discount module's enum type.
     */
    public string $type;

    /**
     * Discount amount in minor units, already clamped and rounded.
     */
    public int $discount_minor;

    public static function fill(array $input): self
    {
        $dto = new self;
        foreach ($input as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->{$key} = $value;
            }
        }

        return $dto;
    }
}
