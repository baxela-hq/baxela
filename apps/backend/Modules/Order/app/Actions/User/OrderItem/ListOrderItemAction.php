<?php

namespace Modules\Order\Actions\User\OrderItem;

use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

class ListOrderItemAction
{
    public function __construct(
        protected CatalogGatewayInterface $catalogGateway,
    ) {}

    public function handle(string $orderId)
    {
        $order = Order::where([
            OrderSchema::ID => $orderId,
            OrderSchema::USER_ID => Auth::id(),
        ])->firstOrfail();

        $items = $order->items;

        // Variant display data (the current product image) is resolved in
        // one batched gateway call and attached for the resource.
        $summaries = $this->catalogGateway->getVariantSummaries(
            $items->pluck(OrderItemSchema::VARIANT_ID)->all()
        );
        $items->each(fn ($item) => $item->setAttribute(
            OrderItemSchema::ATTR_VARIANT_SUMMARY,
            $summaries->get($item->{OrderItemSchema::VARIANT_ID}),
        ));

        return $items;
    }
}
