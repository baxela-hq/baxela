<?php

namespace Modules\Discount\Actions\Admin\Promotion;

use Modules\Discount\Models\Promotion;

class DeletePromotionAction
{
    /**
     * Promotions carry no usage counters and order history snapshots
     * everything it needs (base price, applied discount, promotion id), so
     * deletion is always safe — the pivot rows cascade via FK.
     */
    public function handle(string $id): bool
    {
        return Promotion::query()->findOrFail($id)->delete();
    }
}
