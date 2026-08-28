<?php

namespace Modules\Setting\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Setting\Actions\Admin\Setting\UpdateSettingAction;
use Modules\Setting\Http\Requests\Admin\Setting\SettingRequest;
use Modules\Setting\Transformers\Admin\Setting\SettingResource;

class UpdateSettingController extends Controller
{
    public function __construct(protected UpdateSettingAction $action) {}

    public function __invoke(SettingRequest $request): AnonymousResourceCollection
    {
        return SettingResource::collection($this->action->handle($request->validated()));
    }
}
