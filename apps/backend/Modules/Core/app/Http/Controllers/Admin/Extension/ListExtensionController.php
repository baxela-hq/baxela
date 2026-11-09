<?php

namespace Modules\Core\Http\Controllers\Admin\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Admin\Extension\ListExtensionsAction;
use Modules\Core\Transformers\Admin\Extension\ExtensionResource;

class ListExtensionController extends Controller
{
    public function __construct(protected ListExtensionsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return ExtensionResource::collection($this->action->handle());
    }
}
