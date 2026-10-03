<?php

namespace Modules\Content\Http\Controllers\Admin\PostComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\PostComment\DeletePostCommentAction;
use Symfony\Component\HttpFoundation\Response;

class DeletePostCommentController extends Controller
{
    public function __construct(protected DeletePostCommentAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
