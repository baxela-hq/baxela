<?php

namespace Modules\Catalog\Actions\User\ProductComment;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Exceptions\ProductComment\ReactionFailedException;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Models\ProductCommentReaction;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema as RSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Core\Utils\Auth;
use Throwable;

class ReactToProductCommentAction
{
    /**
     * Toggle semantics: re-sending the same reaction removes it,
     * sending the other one switches it. Returns the caller's new
     * state plus the refreshed counts.
     *
     * @param  array{reaction: string}  $data
     * @return array{reaction: ?ProductCommentReactionEnum, likes_count: int, dislikes_count: int}
     *
     * @throws ReactionFailedException
     */
    public function handle(string $commentId, array $data): array
    {
        $comment = ProductComment::query()->findOrFail($commentId);

        $reaction = ProductCommentReactionEnum::from($data[RSchema::REACTION]);

        try {
            $state = DB::transaction(function () use ($comment, $reaction): ?ProductCommentReactionEnum {
                $existing = ProductCommentReaction::query()
                    ->where(RSchema::PRODUCT_COMMENT_ID, $comment->{Schema::ID})
                    ->where(RSchema::USER_ID, Auth::id())
                    ->lockForUpdate()
                    ->first();

                if (is_null($existing)) {
                    ProductCommentReaction::query()->create([
                        RSchema::PRODUCT_COMMENT_ID => $comment->{Schema::ID},
                        RSchema::USER_ID => Auth::id(),
                        RSchema::REACTION => $reaction,
                    ]);

                    return $reaction;
                }

                if ($existing->{RSchema::REACTION} === $reaction) {
                    $existing->delete();

                    return null;
                }

                $existing->update([RSchema::REACTION => $reaction]);

                return $reaction;
            });
        } catch (Throwable $e) {
            report($e);
            throw new ReactionFailedException;
        }

        return [
            'reaction' => $state,
            ...$this->counts($comment),
        ];
    }

    /**
     * @return array{likes_count: int, dislikes_count: int}
     */
    private function counts(ProductComment $comment): array
    {
        $base = ProductCommentReaction::query()
            ->where(RSchema::PRODUCT_COMMENT_ID, $comment->{Schema::ID});

        return [
            Schema::RES_LIKES_COUNT => (clone $base)
                ->where(RSchema::REACTION, ProductCommentReactionEnum::LIKE)
                ->count(),
            Schema::RES_DISLIKES_COUNT => (clone $base)
                ->where(RSchema::REACTION, ProductCommentReactionEnum::DISLIKE)
                ->count(),
        ];
    }
}
