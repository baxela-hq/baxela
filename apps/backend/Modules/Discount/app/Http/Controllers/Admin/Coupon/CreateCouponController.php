<?php

namespace Modules\Discount\Http\Controllers\Admin\Coupon;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Discount\Actions\Admin\Coupon\CreateCouponAction;
use Modules\Discount\Http\Requests\Admin\Coupon\CouponRequest;
use Modules\Discount\Transformers\Admin\Coupon\CouponResource;

class CreateCouponController extends Controller
{
    public function __construct(protected CreateCouponAction $action) {}

    public function __invoke(CouponRequest $request): JsonResponse
    {
        return CouponResource::make($this->action->handle($request->validated()))
            ->response()
            ->setStatusCode(201);
    }
}
