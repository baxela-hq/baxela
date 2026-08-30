<?php

namespace Modules\Shipping\Actions\Admin\Method;

use Modules\Shipping\Models\Method;

class DeleteMethodAction
{
    public function handle(string $id): bool
    {
        $record = Method::query()->findOrFail($id);

        return $record->delete();
    }
}
