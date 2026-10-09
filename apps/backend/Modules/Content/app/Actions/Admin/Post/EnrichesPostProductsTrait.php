<?php

namespace Modules\Content\Actions\Admin\Post;

use Modules\Content\Models\Post;
use Modules\Content\Models\PostProduct;
use Modules\Content\Schemas\Post\PostProductSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;

trait EnrichesPostProductsTrait
{
    /**
     * Attaches Catalog gateway summaries to the loaded product pivots so
     * the resource can expose titles without importing a Catalog model.
     */
    protected function enrichWithProductSummaries(Post $post): void
    {
        if (! $post->relationLoaded(PostSchema::RES_PRODUCTS)) {
            return;
        }

        $products = $post->getRelation(PostSchema::RES_PRODUCTS);
        if ($products->isEmpty()) {
            return;
        }

        $summaries = app(CatalogGatewayInterface::class)->getProductSummaries(
            $products->pluck(PostProductSchema::PRODUCT_ID)->unique()->values()->all()
        );

        $products->each(function (PostProduct $product) use ($summaries) {
            $product->setAttribute(
                PostProductSchema::ATTR_PRODUCT_SUMMARY,
                $summaries->get($product->{PostProductSchema::PRODUCT_ID})
            );
        });
    }
}
