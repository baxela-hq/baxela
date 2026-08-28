<?php

namespace Modules\Order\Gateways;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Order\Gateways\DTOs\GetOrderOutput;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

class OrderGateway implements OrderGatewayInterface
{
    /**
     * @param array{
     *     variant_id: int,
     *     price_snapshot: string,
     *     product_name_snapshot: string,
     *     quantity: int
     * } $cartItems
     */
    public function createFromCart(array $cartItems): ?int
    {
        try {
            DB::beginTransaction();

            $order = Order::query()->create([
                OrderSchema::USER_ID => Auth::id(),
                OrderSchema::STATUS => OrderStatusEnum::PENDING_PAYMENT,
                OrderSchema::TOTAL_AMOUNT => 0,
                OrderSchema::EXPIRES_AT => now()->addMinutes(30),
            ]);

            $totalAmount = 0;
            foreach ($cartItems as $cartItem) {
                $order->items()->create([
                    OrderItemSchema::VARIANT_ID => $cartItem[OrderItemSchema::VARIANT_ID],
                    OrderItemSchema::PRICE_SNAPSHOT => $cartItem[OrderItemSchema::PRICE_SNAPSHOT],
                    OrderItemSchema::PRODUCT_NAME_SNAPSHOT => $cartItem[OrderItemSchema::PRODUCT_NAME_SNAPSHOT],
                    OrderItemSchema::QUANTITY => $cartItem[OrderItemSchema::QUANTITY],
                ]);

                $totalAmount += $cartItem[OrderItemSchema::PRICE_SNAPSHOT] * $cartItem[OrderItemSchema::QUANTITY];
            }

            $order->{OrderSchema::TOTAL_AMOUNT} = $totalAmount;
            $order->save();

            event(OrderCreatedEvent::fill($order->toArray()));

            DB::commit();

            $orderId = $order->{OrderSchema::ID};
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(
                sprintf('createFromCart failed error:%s line:%s code:%s',
                    $e->getMessage(), $e->getLine(), $e->getCode())
            );
            $orderId = null;
        }

        return $orderId;
    }

    public function getOrder(string $orderId, string $userId): ?GetOrderOutput
    {
        $order = Order::query()
            ->where(OrderSchema::ID, $orderId)
            ->where(OrderSchema::EXPIRES_AT, '<=', now())
            ->first();

        return $order ? new GetOrderOutput($order->toArray()) : null;
    }
}
