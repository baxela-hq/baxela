<?php

namespace Modules\Cart\Transformers\User\Coupon;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Contracts\Gateways\Discount\DTOs\CouponDiscountResult;
use Modules\Core\Support\Money;

/**
 * @mixin CouponDiscountResult|null
 */
class AppliedCouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $discount = $this->resource;

        return [
            'coupon' => is_null($discount) ? null : [
                'code' => $discount->code,
                'type' => $discount->type,
            ],
            'discount_amount' => is_null($discount)
                ? Money::toDecimal(0)
                : Money::toDecimal($discount->discount_minor),
        ];
    }
}
