<?php

namespace Modules\Catalog\Search;

use RuntimeException;

/**
 * The product ID was retired (postgres-migration §3.4.1a): its cursor row
 * was pruned through the lock-serialized maintenance protocol and the ID
 * is permanently barred from further journal mutation or restoration —
 * bigserial ids are never reallocated, so a retired ID cannot legitimately
 * reappear. Surfacing this as a distinct type keeps retirement unmistakable
 * in logs and audits.
 */
class CursorRetiredException extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("search_product_retired:{$productId}");
    }
}
