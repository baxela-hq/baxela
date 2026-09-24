<?php

namespace Modules\Discount\Http\Controllers\Admin\Coupon;

use App\Http\Controllers\Controller;
use Modules\Discount\Actions\Admin\Coupon\UpdateCouponAction;
use Modules\Discount\Http\Requests\Admin\Coupon\CouponRequest;
use Modules\Discount\Transformers\Admin\Coupon\CouponResource;

class UpdateCouponController extends Controller
{
    public function __construct(protected UpdateCouponAction $action) {}

    public function __invoke(CouponRequest $request, string $id): CouponResource
    {
        return CouponResource::make($this->action->handle($id, $request->validated()));
    }
}
