<?php

namespace Modules\Discount\Http\Controllers\Admin\Coupon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Discount\Actions\Admin\Coupon\ListCouponAction;
use Modules\Discount\Transformers\Admin\Coupon\CouponResource;

class ListCouponController extends Controller
{
    public function __construct(protected ListCouponAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return CouponResource::collection($this->action->handle());
    }
}
