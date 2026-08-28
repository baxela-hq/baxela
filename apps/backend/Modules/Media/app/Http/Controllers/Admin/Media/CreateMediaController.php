<?php

namespace Modules\Media\Http\Controllers\Admin\Media;

use App\Http\Controllers\Controller;
use Modules\Media\Actions\Admin\Media\CreateMediaAction;
use Modules\Media\Exceptions\Admin\Media\CreationFailedException;
use Modules\Media\Http\Requests\Admin\Media\MediaRequest;
use Modules\Media\Transformers\Admin\Media\MediaResource;

class CreateMediaController extends Controller
{
    public function __construct(protected CreateMediaAction $action) {}

    /**
     * @throws CreationFailedException
     */
    public function __invoke(MediaRequest $request): MediaResource
    {
        $dto = $request->toDto();

        return MediaResource::make($this->action->handle($dto));
    }
}
