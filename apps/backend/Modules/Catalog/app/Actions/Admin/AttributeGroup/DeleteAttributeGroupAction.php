<?php

namespace Modules\Catalog\Actions\Admin\AttributeGroup;

use Modules\Catalog\Exceptions\AttributeGroup\NotEmptyException;
use Modules\Catalog\Models\AttributeGroup;

class DeleteAttributeGroupAction
{
    /**
     * @throws NotEmptyException
     */
    public function handle(string $id): bool
    {
        $record = AttributeGroup::query()->findOrFail($id);

        if ($record->attributes()->exists()) {
            throw new NotEmptyException;
        }

        return $record->delete();
    }
}
