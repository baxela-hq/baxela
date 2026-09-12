<?php

namespace Modules\Catalog\Actions\Admin\OptionValue;

use Modules\Catalog\Models\Option;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;

class CreateOptionValueAction
{
    public function handle(string $id, array $data): ?OptionValue
    {
        // Resolve the parent first so an unknown option 404s instead of
        // failing on the foreign key at insert time.
        Option::query()->findOrFail($id);

        $body = [
            OptionValueSchema::OPTION_ID => $id,
            OptionValueSchema::POSITION => $data[OptionValueSchema::POSITION],
        ];

        $record = OptionValue::query()->create($body);

        $translations = $data[OptionValueSchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $record->translations()->create($translation);
        }

        return $record->load(OptionValueSchema::RES_TRANSLATIONS);
    }
}
