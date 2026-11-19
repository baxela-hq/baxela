<?php

namespace Modules\Catalog\Observers;

use Modules\Catalog\Models\Variant;
use Modules\Core\Contracts\Events\Catalog\ProductUpdatedEvent;

/**
 * Variant lifecycle → parent-product search reindex. The search document
 * is product-level (price bounds, option-value union), so ANY variant
 * change must refresh the parent's document. Dispatches the product
 * event; the queued afterCommit indexing listener resolves the durable
 * journal entry written by the mutation transaction itself.
 */
final class VariantObserver
{
    public function created(Variant $variant): void
    {
        $this->refreshParent($variant);
    }

    public function updated(Variant $variant): void
    {
        $this->refreshParent($variant);
    }

    public function deleted(Variant $variant): void
    {
        $this->refreshParent($variant);
    }

    private function refreshParent(Variant $variant): void
    {
        $product = $variant->product()->first();

        if ($product === null) {
            return;
        }

        event(ProductUpdatedEvent::fill($product->toArray()));
    }
}
