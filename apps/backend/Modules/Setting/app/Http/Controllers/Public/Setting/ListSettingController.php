<?php

namespace Modules\Setting\Http\Controllers\Public\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Setting\Actions\Public\Setting\ListSettingAction;
use Modules\Setting\Transformers\Public\Setting\SettingResource;

class ListSettingController extends Controller
{
    public function __construct(protected ListSettingAction $action) {}

    public function __invoke(Request $request)
    {
        return SettingResource::collection($this->action->handle($request));
    }
}
