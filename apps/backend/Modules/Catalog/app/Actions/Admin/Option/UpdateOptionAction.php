<?php

namespace Modules\Catalog\Actions\Admin\Option;

use Modules\Catalog\Models\Option;
use Modules\Catalog\Schemas\Option\OptionSchema;

class UpdateOptionAction
{
    public function handle(string $id, array $data): Option
    {
        $category = Option::query()->findOrFail($id);
        $body = [
            OptionSchema::POSITION => $data[OptionSchema::POSITION],
        ];
        $category->update($body);

        $category->translations()->delete();
        $translations = $data[OptionSchema::RES_TRANSLATIONS];
        foreach ($translations as $translation) {
            $category->translations()->create($translation);
        }

        return $category->load(
            OptionSchema::RES_TRANSLATIONS,
            OptionSchema::RES_VALUES.'.'.OptionSchema::RES_TRANSLATIONS
        );
    }
}
