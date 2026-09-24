<?php

namespace Modules\Discount\Actions\Admin\Coupon;

use Modules\Discount\Models\Coupon;

class ShowCouponAction
{
    public function handle(string $id): Coupon
    {
        return Coupon::query()->findOrFail($id);
    }
}
