<?php

namespace Modules\Discount\Http\Controllers\Admin\Coupon;

use App\Http\Controllers\Controller;
use Modules\Discount\Actions\Admin\Coupon\ShowCouponAction;
use Modules\Discount\Transformers\Admin\Coupon\CouponResource;

class ShowCouponController extends Controller
{
    public function __construct(protected ShowCouponAction $action) {}

    public function __invoke(string $id): CouponResource
    {
        return CouponResource::make($this->action->handle($id));
    }
}
