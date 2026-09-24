<?php

namespace Modules\Discount\Actions\Admin\Coupon;

use Illuminate\Support\Facades\DB;
use Modules\Discount\Exceptions\Coupon\UpdateFailedException;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Throwable;

class UpdateCouponAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): Coupon
    {
        /** @var Coupon $record */
        $record = Coupon::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->fill([
                CouponSchema::CODE => $data[CouponSchema::CODE] ?? $record->{CouponSchema::CODE},
                CouponSchema::NAME => $data[CouponSchema::NAME] ?? $record->{CouponSchema::NAME},
                CouponSchema::TYPE => $data[CouponSchema::TYPE] ?? $record->{CouponSchema::TYPE},
                CouponSchema::VALUE => $data[CouponSchema::VALUE] ?? $record->{CouponSchema::VALUE},
                CouponSchema::MAX_DISCOUNT_AMOUNT => $data[CouponSchema::MAX_DISCOUNT_AMOUNT] ?? $record->{CouponSchema::MAX_DISCOUNT_AMOUNT},
                CouponSchema::MIN_ORDER_AMOUNT => $data[CouponSchema::MIN_ORDER_AMOUNT] ?? $record->{CouponSchema::MIN_ORDER_AMOUNT},
                CouponSchema::STARTS_AT => $data[CouponSchema::STARTS_AT] ?? $record->{CouponSchema::STARTS_AT},
                CouponSchema::ENDS_AT => $data[CouponSchema::ENDS_AT] ?? $record->{CouponSchema::ENDS_AT},
                CouponSchema::USAGE_LIMIT => $data[CouponSchema::USAGE_LIMIT] ?? $record->{CouponSchema::USAGE_LIMIT},
                CouponSchema::PER_USER_LIMIT => $data[CouponSchema::PER_USER_LIMIT] ?? $record->{CouponSchema::PER_USER_LIMIT},
                CouponSchema::IS_ACTIVE => $data[CouponSchema::IS_ACTIVE] ?? $record->{CouponSchema::IS_ACTIVE},
            ])->save();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record;
    }
}
