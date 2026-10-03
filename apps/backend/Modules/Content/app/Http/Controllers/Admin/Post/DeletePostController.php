<?php

namespace Modules\Content\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\Post\DeletePostAction;
use Symfony\Component\HttpFoundation\Response;

class DeletePostController extends Controller
{
    public function __construct(protected DeletePostAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
