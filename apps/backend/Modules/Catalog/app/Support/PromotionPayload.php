<?php

namespace Modules\Catalog\Support;

use Modules\Core\Contracts\Gateways\Discount\DTOs\ProductPromotion;

/**
 * The API-facing promotion object customers see next to a price. Only
 * display facts — the configured type/value for badges and countdowns —
 * never admin internals. Consumers compute the actual saving from the
 * prices themselves (compare_price − price), so a clamped fixed discount
 * can never display a misleading amount.
 */
class PromotionPayload
{
    public static function from(?ProductPromotion $promotion): ?array
    {
        if ($promotion === null) {
            return null;
        }

        return [
            'id' => $promotion->promotion_id,
            'type' => $promotion->type,
            'value' => $promotion->value,
            'ends_at' => $promotion->ends_at,
        ];
    }
}
