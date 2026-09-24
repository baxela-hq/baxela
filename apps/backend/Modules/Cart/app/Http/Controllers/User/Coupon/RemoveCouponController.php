<?php

namespace Modules\Cart\Http\Controllers\User\Coupon;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\User\Coupon\RemoveCouponAction;
use Symfony\Component\HttpFoundation\Response;

class RemoveCouponController extends Controller
{
    public function __construct(protected RemoveCouponAction $action) {}

    public function __invoke(): Response
    {
        $this->action->handle();

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
