<?php

namespace Modules\Media\Http\Controllers\Admin\Media;

use App\Http\Controllers\Controller;
use Modules\Media\Actions\Admin\Media\UpdateMediaAction;
use Modules\Media\Http\Requests\Admin\Media\UpdateMediaRequest;
use Modules\Media\Transformers\Admin\Media\MediaResource;

class UpdateMediaController extends Controller
{
    public function __construct(protected UpdateMediaAction $action) {}

    public function __invoke(string $id, UpdateMediaRequest $request): MediaResource
    {
        return MediaResource::make($this->action->handle($id, $request->validated()));
    }
}
