<?php

namespace Modules\Discount\Actions\Admin\Coupon;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListCouponAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = CouponSchema::TABLE.'.'.CouponSchema::ID;

        return QueryBuilder::for(Coupon::class)
            ->allowedFilters(
                AllowedFilter::partial(CouponSchema::CODE),
                AllowedFilter::exact(CouponSchema::TYPE),
                AllowedFilter::exact(CouponSchema::IS_ACTIVE),
            )
            ->allowedSorts(
                CouponSchema::ID,
                CouponSchema::CODE,
                CouponSchema::USAGE_COUNT,
                CouponSchema::ENDS_AT,
            )
            ->select([
                $id,
                CouponSchema::CODE,
                CouponSchema::NAME,
                CouponSchema::TYPE,
                CouponSchema::VALUE,
                CouponSchema::MAX_DISCOUNT_AMOUNT,
                CouponSchema::MIN_ORDER_AMOUNT,
                CouponSchema::STARTS_AT,
                CouponSchema::ENDS_AT,
                CouponSchema::USAGE_LIMIT,
                CouponSchema::PER_USER_LIMIT,
                CouponSchema::USAGE_COUNT,
                CouponSchema::IS_ACTIVE,
                CouponSchema::TABLE.'.'.CouponSchema::CREATED_AT,
                CouponSchema::TABLE.'.'.CouponSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
