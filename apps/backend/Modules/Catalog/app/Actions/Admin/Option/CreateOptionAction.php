<?php

namespace Modules\Catalog\Actions\Admin\Option;

use Modules\Catalog\Models\Option;
use Modules\Catalog\Schemas\Option\OptionSchema;

class CreateOptionAction
{
    public function handle(array $data): ?Option
    {
        $body = [
            OptionSchema::POSITION => $data[OptionSchema::POSITION],
        ];
        $option = Option::query()->create($body);

        $translations = $data[OptionSchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $option->translations()->create($translation);
        }

        return $option->load(OptionSchema::RES_TRANSLATIONS, OptionSchema::RES_VALUES);
    }
}
