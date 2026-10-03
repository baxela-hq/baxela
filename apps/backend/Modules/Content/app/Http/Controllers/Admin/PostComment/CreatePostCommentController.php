<?php

namespace Modules\Content\Http\Controllers\Admin\PostComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Content\Actions\Admin\PostComment\CreatePostCommentAction;
use Modules\Content\Http\Requests\Admin\PostComment\PostCommentRequest;
use Modules\Content\Transformers\Admin\PostComment\PostCommentResource;
use Symfony\Component\HttpFoundation\Response;

class CreatePostCommentController extends Controller
{
    public function __construct(protected CreatePostCommentAction $action) {}

    public function __invoke(PostCommentRequest $request): JsonResponse
    {
        return (new PostCommentResource($this->action->handle($request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
