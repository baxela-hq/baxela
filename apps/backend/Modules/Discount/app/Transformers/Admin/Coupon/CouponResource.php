<?php

namespace Modules\Discount\Transformers\Admin\Coupon;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Discount\Schemas\Coupon\CouponSchema;

class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            CouponSchema::ID => $this->{CouponSchema::ID},
            CouponSchema::CODE => $this->{CouponSchema::CODE},
            CouponSchema::NAME => $this->{CouponSchema::NAME},
            CouponSchema::TYPE => $this->{CouponSchema::TYPE},
            CouponSchema::VALUE => $this->{CouponSchema::VALUE},
            CouponSchema::MAX_DISCOUNT_AMOUNT => $this->{CouponSchema::MAX_DISCOUNT_AMOUNT},
            CouponSchema::MIN_ORDER_AMOUNT => $this->{CouponSchema::MIN_ORDER_AMOUNT},
            CouponSchema::STARTS_AT => $this->{CouponSchema::STARTS_AT},
            CouponSchema::ENDS_AT => $this->{CouponSchema::ENDS_AT},
            CouponSchema::USAGE_LIMIT => $this->{CouponSchema::USAGE_LIMIT},
            CouponSchema::PER_USER_LIMIT => $this->{CouponSchema::PER_USER_LIMIT},
            CouponSchema::USAGE_COUNT => $this->{CouponSchema::USAGE_COUNT},
            CouponSchema::IS_ACTIVE => $this->{CouponSchema::IS_ACTIVE},
            CouponSchema::CREATED_AT => $this->{CouponSchema::CREATED_AT},
            CouponSchema::UPDATED_AT => $this->{CouponSchema::UPDATED_AT},
        ];
    }
}
