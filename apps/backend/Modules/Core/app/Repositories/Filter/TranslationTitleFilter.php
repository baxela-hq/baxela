<?php

namespace Modules\Core\Repositories\Filter;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class TranslationTitleFilter implements Filter
{
    public function __invoke(Builder $query, $value, string $property): void
    {
        $languageId = request()->input('language_id');

        $query->whereHas('translations', function (Builder $query) use ($value, $property, $languageId) {
            $query->where($property, 'LIKE', "%{$value}%");

            if (intval($languageId)) {
                $query->where('language_id', $languageId);
            }
        });
    }
}
