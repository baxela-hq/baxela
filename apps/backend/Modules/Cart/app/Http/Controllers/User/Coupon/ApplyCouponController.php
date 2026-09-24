<?php

namespace Modules\Cart\Http\Controllers\User\Coupon;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\User\Coupon\ApplyCouponAction;
use Modules\Cart\Http\Requests\User\Coupon\ApplyCouponRequest;
use Modules\Cart\Transformers\User\Coupon\AppliedCouponResource;

class ApplyCouponController extends Controller
{
    public function __construct(protected ApplyCouponAction $action) {}

    public function __invoke(ApplyCouponRequest $request): AppliedCouponResource
    {
        return AppliedCouponResource::make($this->action->handle($request->validated('code')));
    }
}
