<?php

namespace Modules\Catalog\Actions\Admin\AttributeTemplate;

use Modules\Catalog\Models\AttributeTemplate;

class DeleteAttributeTemplateAction
{
    public function handle(string $id): bool
    {
        $record = AttributeTemplate::query()->findOrFail($id);

        return $record->delete();
    }
}
