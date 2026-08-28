<?php

namespace Modules\Cart\Http\Controllers\User\Cart;

use App\Http\Controllers\Controller;
use Modules\Cart\Actions\User\Cart\CheckoutAction;
use Modules\Cart\Exceptions\User\Checkout\EmptyCardException;
use Modules\Cart\Exceptions\User\Checkout\InvalidAddressException;
use Modules\Cart\Exceptions\User\Checkout\OrderFailedException;
use Modules\Cart\Exceptions\User\Checkout\OutOfStockException;
use Modules\Cart\Http\Requests\User\Cart\CheckoutRequest;
use Modules\Cart\Transformers\User\Cart\CheckoutResource;

class CheckoutController extends Controller
{
    public function __construct(protected CheckoutAction $action) {}

    /**
     * @throws EmptyCardException
     * @throws InvalidAddressException
     * @throws OrderFailedException
     * @throws OutOfStockException
     */
    public function __invoke(CheckoutRequest $request): CheckoutResource
    {
        $response = new \StdClass;
        $response->order_id = $this->action->handle($request);

        return CheckoutResource::make($response);
    }
}
