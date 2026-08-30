<?php

namespace Modules\Shipping\Actions\Admin\Zone;

use Modules\Shipping\Models\Zone;

class DeleteZoneAction
{
    public function handle(string $id): bool
    {
        $record = Zone::query()->findOrFail($id);

        return $record->delete();
    }
}
