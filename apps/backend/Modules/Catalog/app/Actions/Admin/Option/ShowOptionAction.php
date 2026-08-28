<?php

namespace Modules\Catalog\Actions\Admin\Option;

use Modules\Catalog\Models\Option;
use Modules\Catalog\Schemas\Option\OptionSchema;

class ShowOptionAction
{
    public function handle(string $id): Option
    {
        return Option::query()
            ->with(
                OptionSchema::RES_TRANSLATIONS,
                OptionSchema::RES_VALUES.'.'.OptionSchema::RES_TRANSLATIONS
            )
            ->findOrFail($id);
    }
}
