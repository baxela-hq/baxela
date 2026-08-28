<?php

namespace Modules\Media\Http\Controllers\Admin\Folder;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Media\Actions\Admin\Folder\ListFolderAction;
use Modules\Media\Transformers\Admin\Folder\FolderResource;

class ListFolderController extends Controller
{
    public function __construct(protected ListFolderAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return FolderResource::collection($this->action->handle());
    }
}
