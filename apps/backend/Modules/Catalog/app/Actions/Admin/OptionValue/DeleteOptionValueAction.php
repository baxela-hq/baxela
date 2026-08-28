<?php

namespace Modules\Catalog\Actions\Admin\OptionValue;

use Modules\Catalog\Models\OptionValue;

class DeleteOptionValueAction
{
    public function handle(string $id): bool
    {
        $record = OptionValue::query()->findOrFail($id);

        return $record->delete();
    }
}
