<?php

namespace Modules\Media\Http\Controllers\Admin\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Media\Actions\Admin\Media\ListMediaAction;
use Modules\Media\Transformers\Admin\Media\MediaResource;

class ListMediaController extends Controller
{
    public function __construct(protected ListMediaAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return MediaResource::collection($this->action->handle());
    }
}
