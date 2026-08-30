<?php

namespace Modules\Shipping\Actions\Admin\Rate;

use Modules\Shipping\Models\Rate;

class DeleteRateAction
{
    public function handle(string $id): bool
    {
        $record = Rate::query()->findOrFail($id);

        return $record->delete();
    }
}
