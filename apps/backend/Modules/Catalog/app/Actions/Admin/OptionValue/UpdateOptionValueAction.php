<?php

namespace Modules\Catalog\Actions\Admin\OptionValue;

use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema as Schema;

class UpdateOptionValueAction
{
    public function handle(string $id, string $valueId, array $data): OptionValue
    {
        $record = OptionValue::query()->findOrFail($valueId);
        $body = [
            Schema::POSITION => $data[Schema::POSITION],
        ];
        $record->update($body);

        $record->translations()->delete();
        $translations = $data[Schema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        return $record->load(Schema::RES_TRANSLATIONS);
    }
}
