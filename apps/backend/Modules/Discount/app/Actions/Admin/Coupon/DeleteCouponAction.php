<?php

namespace Modules\Discount\Actions\Admin\Coupon;

use Modules\Discount\Exceptions\Coupon\HasRedemptionsException;
use Modules\Discount\Models\Coupon;
use Modules\Discount\Models\Redemption;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Redemption\RedemptionSchema;

class DeleteCouponAction
{
    /**
     * Refuses deletion when ANY redemption row exists — not when
     * usage_count > 0. Released redemptions (unpaid cancels) are deleted
     * rows but still count as history, and the FK cascade would silently
     * erase them.
     *
     * @throws HasRedemptionsException
     */
    public function handle(string $id): bool
    {
        $record = Coupon::query()->findOrFail($id);

        if (Redemption::query()
            ->where(RedemptionSchema::COUPON_ID, $record->{CouponSchema::ID})
            ->exists()) {
            throw new HasRedemptionsException;
        }

        return $record->delete();
    }
}
