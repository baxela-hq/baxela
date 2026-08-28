<?php

namespace Modules\Catalog\Actions\Admin\Option;

use Modules\Catalog\Models\Option;

class DeleteOptionAction
{
    public function handle(string $id): bool
    {
        $record = Option::query()->findOrFail($id);

        return $record->delete();
    }
}
