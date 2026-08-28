<?php

namespace Modules\Catalog\Actions\Admin\AttributeGroup;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\AttributeGroup\UpdateFailedException;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Throwable;

class UpdateAttributeGroupAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): AttributeGroup
    {
        $record = AttributeGroup::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                AttributeGroupSchema::POSITION => $data[AttributeGroupSchema::POSITION],
            ]);

            $record->translations()->delete();
            foreach ($data[AttributeGroupSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record->load(
            AttributeGroupSchema::RES_TRANSLATIONS,
            AttributeGroupSchema::RES_ATTRIBUTES.'.'.AttributeSchema::RES_TRANSLATIONS,
        );
    }
}
