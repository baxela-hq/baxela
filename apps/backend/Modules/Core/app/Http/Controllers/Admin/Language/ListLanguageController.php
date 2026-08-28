<?php

namespace Modules\Core\Http\Controllers\Admin\Language;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Admin\Language\ListLanguageAction;
use Modules\Core\Transformers\Admin\Language\LanguageResource;

class ListLanguageController extends Controller
{
    public function __construct(protected ListLanguageAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return LanguageResource::collection($this->action->handle());
    }
}
