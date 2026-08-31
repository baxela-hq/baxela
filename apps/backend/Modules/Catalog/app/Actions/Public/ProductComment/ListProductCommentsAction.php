<?php

namespace Modules\Catalog\Actions\Public\ProductComment;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;

class ListProductCommentsAction
{
    public function __construct(protected UserGatewayInterface $userGateway) {}

    public function handle(string $productId): LengthAwarePaginator
    {
        $comments = ProductComment::query()
            ->where(Schema::PRODUCT_ID, $productId)
            ->whereNull(Schema::PARENT_ID)
            ->where(Schema::STATUS, ProductCommentStatusEnum::APPROVED)
            ->with([
                Schema::RES_REPLIES => fn (Builder $query) => $query->where(
                    Schema::STATUS, ProductCommentStatusEnum::APPROVED),
            ])
            ->orderBy(Schema::ID, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));

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

        $flattened->each(function (ProductComment $comment) use ($names) {
            $comment->setAttribute(Schema::RES_USER, [
                Schema::ID => $comment->{Schema::USER_ID},
                Schema::RES_USER_NAME => $names[$comment->{Schema::USER_ID}] ?? null,
            ]);
        });
    }
}
