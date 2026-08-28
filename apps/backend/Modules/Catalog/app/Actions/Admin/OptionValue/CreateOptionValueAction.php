<?php

namespace Modules\Catalog\Actions\Admin\OptionValue;

use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;

class CreateOptionValueAction
{
    public function handle(string $id, array $data): ?OptionValue
    {
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
