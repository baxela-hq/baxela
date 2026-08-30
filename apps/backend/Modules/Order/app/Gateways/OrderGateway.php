<?php

namespace Modules\Order\Gateways;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Events\Order\OrderCompletedEvent;
use Modules\Core\Contracts\Events\Order\OrderCreatedEvent;
use Modules\Core\Contracts\Events\Order\OrderShippedEvent;
use Modules\Core\Contracts\Gateways\Order\DTOs\CreateOrderInput;
use Modules\Core\Contracts\Gateways\Order\OrderGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Order\Gateways\DTOs\GetOrderOutput;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\Order\OrderStatusEnum;
use Modules\Order\Schemas\OrderAddress\OrderAddressSchema;
use Modules\Order\Schemas\OrderAddress\OrderAddressTypeEnum;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

class OrderGateway implements OrderGatewayInterface
{
    public function createFromCart(CreateOrderInput $input): ?int
    {
        try {
            DB::beginTransaction();

            $order = Order::query()->create([
                OrderSchema::USER_ID => Auth::id(),
                OrderSchema::STATUS => OrderStatusEnum::PENDING_PAYMENT,
                OrderSchema::TOTAL_AMOUNT => 0,
                OrderSchema::SHIPPING_METHOD_ID => $input->shipping_method_id,
                OrderSchema::SHIPPING_METHOD_NAME => $input->shipping_method_name,
                OrderSchema::SHIPPING_COST => $input->shipping_cost,
                OrderSchema::EXPIRES_AT => now()->addMinutes(30),
            ]);

            $totalAmount = 0;
            foreach ($input->cart_items as $cartItem) {
                $order->items()->create([
                    OrderItemSchema::VARIANT_ID => $cartItem[OrderItemSchema::VARIANT_ID],
                    OrderItemSchema::PRICE_SNAPSHOT => $cartItem[OrderItemSchema::PRICE_SNAPSHOT],
                    OrderItemSchema::PRODUCT_NAME_SNAPSHOT => $cartItem[OrderItemSchema::PRODUCT_NAME_SNAPSHOT],
                    OrderItemSchema::QUANTITY => $cartItem[OrderItemSchema::QUANTITY],
                ]);

                $totalAmount += $cartItem[OrderItemSchema::PRICE_SNAPSHOT] * $cartItem[OrderItemSchema::QUANTITY];
            }

            if (! is_null($input->address)) {
                $order->addresses()->create([
                    OrderAddressSchema::TYPE => OrderAddressTypeEnum::SHIPPING,
                    OrderAddressSchema::FULL_NAME => $input->address->full_name,
                    OrderAddressSchema::PHONE => $input->address->phone,
                    OrderAddressSchema::ADDRESS_LINE => $input->address->address_line,
                    OrderAddressSchema::CITY => $input->address->city,
                    OrderAddressSchema::POSTAL_CODE => $input->address->postal_code,
                    OrderAddressSchema::COUNTRY_CODE => $input->address->country_code,
                ]);
            }

            $totalAmount += $input->shipping_cost;

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

    public function findOrder(int $orderId): ?GetOrderOutput
    {
        $order = Order::query()
            ->where(OrderSchema::ID, $orderId)
            ->first();

        return $order ? new GetOrderOutput($order->toArray()) : null;
    }

    public function markAsShipped(int $orderId): bool
    {
        return $this->updateStatus($orderId, OrderStatusEnum::SHIPPED, OrderShippedEvent::class);
    }

    public function markAsDelivered(int $orderId): bool
    {
        return $this->updateStatus($orderId, OrderStatusEnum::COMPLETED, OrderCompletedEvent::class);
    }

    /**
     * @param  class-string<OrderShippedEvent|OrderCompletedEvent>  $event
     */
    private function updateStatus(int $orderId, OrderStatusEnum $status, string $event): bool
    {
        $order = Order::query()->where(OrderSchema::ID, $orderId)->first();

        if (is_null($order)) {
            return false;
        }

        $order->{OrderSchema::STATUS} = $status;
        $order->save();

        event($event::fill([
            OrderSchema::ID => $order->{OrderSchema::ID},
            OrderSchema::USER_ID => $order->{OrderSchema::USER_ID},
            OrderSchema::STATUS => $status->value,
        ]));

        return true;
    }
}
