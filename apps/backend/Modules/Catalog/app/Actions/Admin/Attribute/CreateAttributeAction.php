<?php

namespace Modules\Catalog\Actions\Admin\Attribute;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\Attribute\CreationFailedException;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Throwable;

class CreateAttributeAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Attribute
    {
        try {
            DB::beginTransaction();

            $record = Attribute::query()->create([
                AttributeSchema::GROUP_ID => $data[AttributeSchema::GROUP_ID],
                AttributeSchema::CODE => $data[AttributeSchema::CODE],
                AttributeSchema::DATA_TYPE => $data[AttributeSchema::DATA_TYPE],
                AttributeSchema::IS_FILTERABLE => $data[AttributeSchema::IS_FILTERABLE],
                AttributeSchema::POSITION => $data[AttributeSchema::POSITION],
            ]);

            foreach ($data[AttributeSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record->load(
            AttributeSchema::RES_TRANSLATIONS,
            AttributeSchema::RES_VALUES.'.'.AttributeValueSchema::RES_TRANSLATIONS,
            AttributeSchema::RES_GROUP.'.'.AttributeGroupSchema::RES_TRANSLATIONS,
        );
    }
}
