<?php

namespace Modules\Core\Actions\Public\Language;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

class ListActiveLanguagesAction
{
    public function handle(): Collection
    {
        return Language::query()
            ->where(LanguageSchema::IS_ACTIVE, true)
            ->orderBy(LanguageSchema::POSITION)
            ->get();
    }
}
