<?php

namespace Modules\Core\Http\Controllers\Admin\Timezone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Actions\Admin\Timezone\ListTimezoneAction;

class ListTimezoneController extends Controller
{
    public function __construct(protected ListTimezoneAction $action) {}

    public function __invoke(): JsonResource
    {
        return JsonResource::make($this->action->handle());
    }
}
