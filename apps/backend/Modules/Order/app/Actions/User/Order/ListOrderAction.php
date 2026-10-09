<?php

namespace Modules\Order\Actions\User\Order;

use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Order\Models\Order;
use Modules\Order\Schemas\Order\OrderSchema;
use Modules\Order\Schemas\OrderItem\OrderItemSchema;

class ListOrderAction extends AbstractOrderAction
{
    public function __construct(
        protected CatalogGatewayInterface $catalogGateway,
        Order $order,
    ) {
        parent::__construct($order);
    }

    public function handle()
    {
        $paginator = $this->order
            ->where(OrderSchema::USER_ID, Auth::id())
            // The resource only serializes relations when loaded; the
            // storefront order list renders them in its detail view.
            ->with(OrderSchema::RES_ADDRESSES)
            // Item name snapshots feed the storefront's ticket order picker;
            // the resource skips them for callers that don't load them.
            ->with(OrderSchema::RES_ITEMS)
            ->orderByDesc(OrderSchema::ID)
            ->paginate(15)
            ->withQueryString();

        // Variant display data (the current product image) is resolved in
        // one batched gateway call across every order on the page and
        // attached for the resource — same contract as the per-order items
        // endpoint.
        $variantIds = $paginator
            ->getCollection()
            ->flatMap(fn ($order) => $order->{OrderSchema::RES_ITEMS}->pluck(OrderItemSchema::VARIANT_ID))
            ->unique()
            ->values()
            ->all();

        $summaries = $this->catalogGateway->getVariantSummaries($variantIds);

        $paginator->getCollection()->each(function ($order) use ($summaries) {
            $order->{OrderSchema::RES_ITEMS}->each(fn ($item) => $item->setAttribute(
                OrderItemSchema::ATTR_VARIANT_SUMMARY,
                $summaries->get($item->{OrderItemSchema::VARIANT_ID}),
            ));
        });

        return $paginator;
    }
}
