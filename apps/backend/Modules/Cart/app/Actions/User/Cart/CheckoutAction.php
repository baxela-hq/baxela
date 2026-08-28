<?php

namespace Modules\Cart\Actions\User\Cart;

use Modules\Cart\Exceptions\User\Checkout\EmptyCardException;
use Modules\Cart\Exceptions\User\Checkout\InvalidAddressException;
use Modules\Cart\Exceptions\User\Checkout\OrderFailedException;
use Modules\Cart\Exceptions\User\Checkout\OutOfStockException;
use Modules\Cart\Http\Requests\User\Cart\CheckoutRequest;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Events\Cart\CartCheckedOutEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Core\Utils\Auth;

class CheckoutAction
{
    public function __construct(protected UserGatewayInterface $userGateway) {}

    /**
     * @return int OrderId
     *
     * @throws EmptyCardException
     * @throws InvalidAddressException
     * @throws OrderFailedException
     * @throws OutOfStockException
     */
    public function handle(CheckoutRequest $request): int
    {
        $cart = Cart::query()->where(CartSchema::USER_ID, Auth::id())->first();
        $cartItems = $cart?->items;

        if (is_null($cart) || $cartItems->isEmpty()) {
            throw new EmptyCardException;
        }

        $UserGateway = app(UserGatewayInterface::class);
        if (! $UserGateway->isUserAddressValid(Auth::id(), $request->input('address_id'))) {
            throw new InvalidAddressException;
        }

        $inventoryGateway = app(InventoryGatewayInterface::class);
        foreach ($cartItems as $cartItem) {
            if (! $inventoryGateway->checkAvailability(
                $cartItem->{CartItemSchema::VARIANT_ID}, $cartItem->{CartItemSchema::QUANTITY})) {
                throw new OutOfStockException(meta: [
                    CartItemSchema::VARIANT_ID => $cartItem->{CartItemSchema::VARIANT_ID},
                ]);
            }
        }

        $orderGateway = app(OrderGatewayInterface::class);
        $orderId = $orderGateway->createFromCart($cartItems->toArray());
        if (! $orderId) {
            throw new OrderFailedException;
        }

        $cart->items()->delete();
        $cart->delete();

        event(CartCheckedOutEvent::fill($cart->toArray()));

        return $orderId;
    }
}
