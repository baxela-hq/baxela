<?php

namespace Modules\Catalog\Actions\Admin\Attribute;

use Modules\Catalog\Models\Attribute;

class DeleteAttributeAction
{
    public function handle(string $id): bool
    {
        $record = Attribute::query()->findOrFail($id);

        return $record->delete();
    }
}
