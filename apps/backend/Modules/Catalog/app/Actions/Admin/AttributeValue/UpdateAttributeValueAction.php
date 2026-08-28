<?php

namespace Modules\Catalog\Actions\Admin\AttributeValue;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\AttributeValue\UpdateFailedException;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Throwable;

class UpdateAttributeValueAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $attributeId, string $valueId, array $data): AttributeValue
    {
        $record = AttributeValue::query()
            ->where(AttributeValueSchema::ATTRIBUTE_ID, $attributeId)
            ->where(AttributeValueSchema::ID, $valueId)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            $record->update([
                AttributeValueSchema::POSITION => $data[AttributeValueSchema::POSITION],
            ]);

            $record->translations()->delete();
            foreach ($data[AttributeValueSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record->load(AttributeValueSchema::RES_TRANSLATIONS);
    }
}
