<?php

namespace Modules\Core\Actions\Admin\Language;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

class ListLanguageAction
{
    public function handle(): Collection
    {
        return Language::query()
            ->where(LanguageSchema::IS_ACTIVE, true)
            ->orderBy(LanguageSchema::POSITION)
            ->get();
    }
}
