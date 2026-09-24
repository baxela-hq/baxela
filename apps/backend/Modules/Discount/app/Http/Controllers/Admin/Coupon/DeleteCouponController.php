<?php

namespace Modules\Discount\Http\Controllers\Admin\Coupon;

use App\Http\Controllers\Controller;
use Modules\Discount\Actions\Admin\Coupon\DeleteCouponAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteCouponController extends Controller
{
    public function __construct(protected DeleteCouponAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
