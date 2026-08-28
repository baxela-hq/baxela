<?php

namespace Modules\Catalog\Actions\Admin\AttributeValue;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\AttributeValue\CreationFailedException;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Throwable;

class CreateAttributeValueAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(string $attributeId, array $data): AttributeValue
    {
        try {
            DB::beginTransaction();

            $record = AttributeValue::query()->create([
                AttributeValueSchema::ATTRIBUTE_ID => $attributeId,
                AttributeValueSchema::POSITION => $data[AttributeValueSchema::POSITION],
            ]);

            foreach ($data[AttributeValueSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        return $record->load(AttributeValueSchema::RES_TRANSLATIONS);
    }
}
