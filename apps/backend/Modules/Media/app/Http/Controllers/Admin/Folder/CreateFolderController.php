<?php

namespace Modules\Media\Http\Controllers\Admin\Folder;

use App\Http\Controllers\Controller;
use Modules\Media\Actions\Admin\Folder\CreateFolderAction;
use Modules\Media\Http\Requests\Admin\Folder\FolderRequest;
use Modules\Media\Transformers\Admin\Folder\FolderResource;

class CreateFolderController extends Controller
{
    public function __construct(protected CreateFolderAction $action) {}

    public function __invoke(FolderRequest $request): FolderResource
    {
        return FolderResource::make($this->action->handle($request->validated()));
    }
}
