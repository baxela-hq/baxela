<?php

namespace Modules\Core\Actions\Admin\Language;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

class ListLanguageAction
{
    /**
     * Active languages by default (every translation tab consumes this);
     * the management page asks for all rows to reveal inactive ones.
     */
    public function handle(bool $all = false): Collection
    {
        $query = Language::query();

        if (! $all) {
            $query->where(LanguageSchema::IS_ACTIVE, true);
        }

        return $query->orderBy(LanguageSchema::POSITION)->get();
    }
}
