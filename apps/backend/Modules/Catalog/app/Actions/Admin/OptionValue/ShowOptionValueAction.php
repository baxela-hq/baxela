<?php

namespace Modules\Catalog\Actions\Admin\OptionValue;

use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;

class ShowOptionValueAction
{
    public function handle(string $id, string $valueId): OptionValue
    {
        return OptionValue::query()
            ->where(OptionValueSchema::OPTION_ID, $id)
            ->where(OptionValueSchema::ID, $valueId)
            ->with(
                OptionValueSchema::RES_TRANSLATIONS,
            )
            ->firstOrFail();
    }
}
