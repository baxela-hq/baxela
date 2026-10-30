<?php

namespace Modules\Core\Http\Controllers\Public\Language;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Public\Language\ListActiveLanguagesAction;
use Modules\Core\Transformers\Public\Language\ActiveLanguageResource;

class ListLanguageController extends Controller
{
    public function __construct(protected ListActiveLanguagesAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return ActiveLanguageResource::collection($this->action->handle());
    }
}
