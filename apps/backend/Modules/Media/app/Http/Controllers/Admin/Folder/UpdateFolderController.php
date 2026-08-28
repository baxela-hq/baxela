<?php

namespace Modules\Media\Http\Controllers\Admin\Folder;

use App\Http\Controllers\Controller;
use Modules\Media\Actions\Admin\Folder\UpdateFolderAction;
use Modules\Media\Http\Requests\Admin\Folder\FolderRequest;
use Modules\Media\Transformers\Admin\Folder\FolderResource;

class UpdateFolderController extends Controller
{
    public function __construct(protected UpdateFolderAction $action) {}

    public function __invoke(string $id, FolderRequest $request): FolderResource
    {
        return FolderResource::make($this->action->handle($id, $request->validated()));
    }
}
