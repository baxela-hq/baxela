<?php

namespace Modules\Cart\Support;

use Illuminate\Support\Collection;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Contracts\Gateways\Catalog\DTOs\VariantSummary;

/**
 * Write-through price refresh for cart lines. The CURRENT effective price
 * (promotion applied, resolved live by the Catalog gateway) is the policy:
 * every cart read re-syncs price_snapshot so the customer always sees —
 * and is always charged — today's price, never the price frozen at
 * add-to-cart. Promotion windows therefore take effect (and lapse) for
 * existing carts without any scheduled job.
 */
class RefreshesCartPrices
{
    public function __construct(protected CatalogGatewayInterface $catalogGateway) {}

    /**
     * Refresh every item's price_snapshot to the current effective price
     * and attach the resolved summary for the resource. Idempotent; a
     * variant that no longer resolves keeps its snapshot (the item is
     * already unorderable — checkout re-validates stock anyway).
     *
     * @param  Collection<int, \Modules\Cart\Models\CartItem>  $items
     * @return Collection<int, VariantSummary>  keyed by variant id
     */
    public function refresh(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        $summaries = $this->catalogGateway->getVariantSummaries(
            $items->pluck(CartItemSchema::VARIANT_ID)->all()
        );

        foreach ($items as $item) {
            $summary = $summaries->get($item->{CartItemSchema::VARIANT_ID});

            if ($summary !== null) {
                if ($summary->price !== null
                    && (string) $summary->price !== (string) $item->{CartItemSchema::PRICE_SNAPSHOT}) {
                    $item->{CartItemSchema::PRICE_SNAPSHOT} = $summary->price;
                    $item->save();
                }

                $item->setAttribute(CartItemSchema::ATTR_VARIANT_SUMMARY, $summary);
            }
        }

        return $summaries;
    }
}
