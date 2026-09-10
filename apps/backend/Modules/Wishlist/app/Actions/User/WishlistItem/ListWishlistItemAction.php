<?php

namespace Modules\Wishlist\Actions\User\WishlistItem;

use Illuminate\Support\Collection;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Wishlist\Models\WishlistItem;
use Modules\Wishlist\Schemas\WishlistItem\WishlistItemSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListWishlistItemAction
{
    public function __construct(
        protected WishlistItem $wishlistItem,
        protected CatalogGatewayInterface $catalogGateway,
    ) {}

    public function handle(): Collection
    {
        // Ordering is owned here — recently saved first — never by
        // Catalog data. The QueryBuilder wrapper keeps the endpoint one
        // ->paginate() away from pagination later.
        $items = QueryBuilder::for(
            $this->wishlistItem
                ->query()
                ->where(WishlistItemSchema::USER_ID, Auth::id())
                ->latest(WishlistItemSchema::CREATED_AT)
        )
            ->allowedFilters(AllowedFilter::exact(WishlistItemSchema::PRODUCT_ID))
            ->get();

        $summaries = $this->catalogGateway->getProductSummaries(
            $items->pluck(WishlistItemSchema::PRODUCT_ID)->all()
        );

        // Attach the summaries for the resource to map; absent summaries
        // (unavailable products) stay null and the row survives.
        $items->each(fn (WishlistItem $item) => $item->setAttribute(
            WishlistItemSchema::ATTR_PRODUCT_SUMMARY,
            $summaries->get($item->{WishlistItemSchema::PRODUCT_ID}),
        ));

        return $items;
    }
}
