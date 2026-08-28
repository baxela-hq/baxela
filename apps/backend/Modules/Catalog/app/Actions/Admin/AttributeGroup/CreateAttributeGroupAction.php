<?php

namespace Modules\Catalog\Actions\Admin\AttributeGroup;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\AttributeGroup\CreationFailedException;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Throwable;

class CreateAttributeGroupAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): AttributeGroup
    {
        try {
            DB::beginTransaction();

            $record = AttributeGroup::query()->create([
                AttributeGroupSchema::POSITION => $data[AttributeGroupSchema::POSITION],
            ]);

            foreach ($data[AttributeGroupSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record->load(AttributeGroupSchema::RES_TRANSLATIONS);
    }
}
