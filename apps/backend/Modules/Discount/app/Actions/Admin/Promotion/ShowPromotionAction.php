<?php

namespace Modules\Discount\Actions\Admin\Promotion;

use Modules\Discount\Models\Promotion;

class ShowPromotionAction
{
    public function handle(string $id): Promotion
    {
        return Promotion::query()->findOrFail($id);
    }
}
