<?php

namespace Modules\Core\Http\Controllers\Admin\Language;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Actions\Admin\Language\UpdateLanguageAction;
use Modules\Core\Http\Requests\Admin\Language\UpdateLanguageRequest;
use Modules\Core\Transformers\Admin\Language\LanguageResource;

class UpdateLanguageController extends Controller
{
    public function __construct(protected UpdateLanguageAction $action) {}

    public function __invoke(UpdateLanguageRequest $request, string $id): JsonResource
    {
        return LanguageResource::make($this->action->handle($id, $request->validated()));
    }
}
