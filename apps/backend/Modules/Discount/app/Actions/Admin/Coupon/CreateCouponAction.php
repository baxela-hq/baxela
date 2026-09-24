<?php

namespace Modules\Discount\Actions\Admin\Coupon;

use Illuminate\Support\Facades\DB;
use Modules\Discount\Exceptions\Coupon\CreationFailedException;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Throwable;

class CreateCouponAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Coupon
    {
        try {
            DB::beginTransaction();

            $record = Coupon::query()->create([
                CouponSchema::CODE => $data[CouponSchema::CODE],
                CouponSchema::NAME => $data[CouponSchema::NAME] ?? null,
                CouponSchema::TYPE => $data[CouponSchema::TYPE],
                CouponSchema::VALUE => $data[CouponSchema::VALUE],
                CouponSchema::MAX_DISCOUNT_AMOUNT => $data[CouponSchema::MAX_DISCOUNT_AMOUNT] ?? null,
                CouponSchema::MIN_ORDER_AMOUNT => $data[CouponSchema::MIN_ORDER_AMOUNT] ?? null,
                CouponSchema::STARTS_AT => $data[CouponSchema::STARTS_AT] ?? null,
                CouponSchema::ENDS_AT => $data[CouponSchema::ENDS_AT] ?? null,
                CouponSchema::USAGE_LIMIT => $data[CouponSchema::USAGE_LIMIT] ?? null,
                CouponSchema::PER_USER_LIMIT => $data[CouponSchema::PER_USER_LIMIT] ?? null,
                CouponSchema::USAGE_COUNT => 0,
                CouponSchema::IS_ACTIVE => $data[CouponSchema::IS_ACTIVE] ?? true,
            ]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record;
    }
}
