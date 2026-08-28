<?php

namespace Modules\Setting\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Setting\Actions\Admin\Setting\ListSettingAction;
use Modules\Setting\Transformers\Admin\Setting\SettingResource;

class ListSettingController extends Controller
{
    public function __construct(protected ListSettingAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return SettingResource::collection($this->action->handle());
    }
}
