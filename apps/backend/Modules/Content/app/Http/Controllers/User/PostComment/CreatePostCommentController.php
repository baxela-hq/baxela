<?php

namespace Modules\Content\Http\Controllers\User\PostComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Content\Actions\User\PostComment\CreatePostCommentAction;
use Modules\Content\Exceptions\PostComment\CreationFailedException;
use Modules\Content\Exceptions\PostComment\InvalidParentException;
use Modules\Content\Http\Requests\User\PostComment\PostCommentRequest;
use Modules\Content\Transformers\User\PostComment\PostCommentResource;
use Symfony\Component\HttpFoundation\Response;

class CreatePostCommentController extends Controller
{
    public function __construct(protected CreatePostCommentAction $action) {}

    /**
     * @throws InvalidParentException|CreationFailedException
     */
    public function __invoke(string $id, PostCommentRequest $request): JsonResponse
    {
        return (new PostCommentResource($this->action->handle($id, $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
