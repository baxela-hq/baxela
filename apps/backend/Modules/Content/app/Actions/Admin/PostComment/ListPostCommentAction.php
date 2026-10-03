<?php

namespace Modules\Content\Actions\Admin\PostComment;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListPostCommentAction extends AbstractPostCommentAction
{
    public function handle(): LengthAwarePaginator
    {
        $comments = QueryBuilder::for(PostComment::class)
            ->allowedFilters(
                AllowedFilter::exact(Schema::STATUS),
                AllowedFilter::exact(Schema::POST_ID),
            )
            ->allowedSorts(
                Schema::CREATED_AT,
                Schema::STATUS,
            )
            ->select([
                Schema::TABLE.'.'.Schema::ID,
                Schema::TABLE.'.'.Schema::POST_ID,
                Schema::TABLE.'.'.Schema::USER_ID,
                Schema::TABLE.'.'.Schema::PARENT_ID,
                Schema::TABLE.'.'.Schema::BODY,
                Schema::TABLE.'.'.Schema::STATUS,
                Schema::TABLE.'.'.Schema::CREATED_AT,
                Schema::TABLE.'.'.Schema::UPDATED_AT,
            ])
            ->with(Schema::RES_POST.'.'.PostSchema::RES_TRANSLATIONS)
            ->orderBy(Schema::TABLE.'.'.Schema::ID, 'desc')
            ->paginate(Pagination::perPage());

        $this->enrichWithUserNames($comments->getCollection());

        return $comments;
    }
}
