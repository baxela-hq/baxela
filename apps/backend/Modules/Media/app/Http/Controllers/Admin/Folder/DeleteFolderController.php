<?php

namespace Modules\Media\Http\Controllers\Admin\Folder;

use App\Http\Controllers\Controller;
use Modules\Media\Actions\Admin\Folder\DeleteFolderAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteFolderController extends Controller
{
    public function __construct(protected DeleteFolderAction $action) {}

    public function __invoke(string $id): \Illuminate\Http\Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
