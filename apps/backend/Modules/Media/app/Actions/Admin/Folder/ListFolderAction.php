<?php

namespace Modules\Media\Actions\Admin\Folder;

use Illuminate\Database\Eloquent\Collection;
use Modules\Media\Filters\NullableExactFilter;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListFolderAction
{
    public function handle(): Collection
    {
        return QueryBuilder::for(Folder::class)
            ->allowedFilters(
                AllowedFilter::custom(FolderSchema::PARENT_ID, new NullableExactFilter(FolderSchema::PARENT_ID))
                    ->nullable(true),
            )
            ->allowedSorts(
                FolderSchema::ID,
            )
            ->orderBy(FolderSchema::ID, 'desc')
            ->get();
    }
}
