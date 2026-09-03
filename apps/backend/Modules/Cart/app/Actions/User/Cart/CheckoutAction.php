<?php

namespace Modules\Cart\Actions\User\Cart;

use Illuminate\Support\Facades\DB;
use Modules\Cart\Exceptions\User\Checkout\EmptyCardException;
use Modules\Cart\Exceptions\User\Checkout\InvalidAddressException;
use Modules\Cart\Exceptions\User\Checkout\InvalidShippingMethodException;
use Modules\Cart\Exceptions\User\Checkout\OrderFailedException;
use Modules\Cart\Exceptions\User\Checkout\OutOfStockException;
use Modules\Cart\Http\Requests\User\Cart\CheckoutRequest;
use Modules\Cart\Models\Cart;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Events\Cart\CartCheckedOutEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;
use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Contracts\Gateways\Shipping\ShippingGatewayInterface;
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
     * @throws InvalidShippingMethodException
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

        $address = $this->userGateway->getAddress(Auth::id(), $request->input('address_id'));
        if (is_null($address)) {
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

        $input = new CreateOrderInput;
        $input->cart_items = $cartItems->toArray();
        $input->address = $address;

        $shippingMethodId = $request->input('shipping_method_id');
        if (! is_null($shippingMethodId)) {
            $quote = app(ShippingGatewayInterface::class)
                ->getQuote($shippingMethodId, $address->country_code);
            if (is_null($quote)) {
                throw new InvalidShippingMethodException;
            }

            $input->shipping_method_id = $quote->id;
            $input->shipping_method_name = $quote->name;
            $input->shipping_cost = $quote->price;
        }

        $orderGateway = app(OrderGatewayInterface::class);

        // One transaction: the gateway's own transaction nests as a savepoint,
        // so a stock-decrement or cart-teardown failure rolls the freshly
        // created order back too instead of leaving a duplicate-order trap
        // for a retry
        $orderId = DB::transaction(function () use ($orderGateway, $inventoryGateway, $input, $cart, $cartItems): ?int {
            $orderId = $orderGateway->createFromCart($input);
            if (! $orderId) {
                return null;
            }

            foreach ($cartItems as $cartItem) {
                if (! $inventoryGateway->decrement(
                    $cartItem->{CartItemSchema::VARIANT_ID},
                    $cartItem->{CartItemSchema::QUANTITY}
                )) {
                    throw new OutOfStockException(meta: [
                        CartItemSchema::VARIANT_ID => $cartItem->{CartItemSchema::VARIANT_ID},
                    ]);
                }
            }

            $cart->items()->delete();
            $cart->delete();

            return $orderId;
        });

        if (! $orderId) {
            throw new OrderFailedException;
        }

        event(CartCheckedOutEvent::fill($cart->toArray()));

        return $orderId;
    }
}
