<?php

namespace Modules\Content\Actions\Public\PostComment;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Core\Utils\Pagination;

class ListPostCommentsAction
{
    public function __construct(protected UserGatewayInterface $userGateway) {}

    public function handle(string $postId): LengthAwarePaginator
    {
        $comments = PostComment::query()
            ->where(Schema::POST_ID, $postId)
            ->whereNull(Schema::PARENT_ID)
            ->where(Schema::STATUS, PostCommentStatusEnum::APPROVED)
            ->with([
                Schema::RES_REPLIES => fn (Builder $query) => $query->where(
                    Schema::STATUS, PostCommentStatusEnum::APPROVED),
            ])
            ->orderBy(Schema::ID, 'desc')
            ->paginate(Pagination::perPage());

        $this->enrichWithUserNames($comments->getCollection());

        return $comments;
    }

    private function enrichWithUserNames(iterable $comments): void
    {
        $flattened = collect();
        foreach ($comments as $comment) {
            $flattened->push($comment);
            if ($comment->relationLoaded(Schema::RES_REPLIES)) {
                $flattened = $flattened->merge($comment->getRelation(Schema::RES_REPLIES));
            }
        }

        $names = $this->userGateway->getUserNamesByIds(
            $flattened->pluck(Schema::USER_ID)->unique()->values()->all()
        );

        $flattened->each(function (PostComment $comment) use ($names) {
            $comment->setAttribute(Schema::RES_USER, [
                Schema::ID => $comment->{Schema::USER_ID},
                Schema::RES_USER_NAME => $names[$comment->{Schema::USER_ID}] ?? null,
            ]);
        });
    }
}
