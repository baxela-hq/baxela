<?php

namespace Modules\Media\Http\Controllers\Admin\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Media\Actions\Admin\Media\DeleteMediaAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteMediaController extends Controller
{
    public function __construct(protected DeleteMediaAction $action) {}

    public function __invoke(string $id, Request $request): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
