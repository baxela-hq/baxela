<?php

namespace Modules\Cart\Http\Controllers\User\Coupon;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\User\Coupon\ShowAppliedCouponAction;
use Modules\Cart\Transformers\User\Coupon\AppliedCouponResource;

class ShowAppliedCouponController extends Controller
{
    public function __construct(protected ShowAppliedCouponAction $action) {}

    public function __invoke(): AppliedCouponResource
    {
        return AppliedCouponResource::make($this->action->handle());
    }
}
