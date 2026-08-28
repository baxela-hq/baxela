<?php

namespace Modules\Media\Actions\Admin\Media;

use Illuminate\Database\Eloquent\Collection;
use Modules\Media\Filters\NullableExactFilter;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListMediaAction extends AbstractMediaAction
{
    public function handle(): Collection
    {
        return QueryBuilder::for(Media::class)
            ->allowedFilters(
                AllowedFilter::custom(MediaSchema::FOLDER_ID, new NullableExactFilter(MediaSchema::FOLDER_ID))
                    ->nullable(true),
            )
            ->allowedSorts(
                MediaSchema::ID,
            )
            ->orderBy(MediaSchema::ID, 'desc')
            ->get();
    }
}
