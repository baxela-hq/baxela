<?php

namespace Modules\Auth\Http\Controllers\Admin\Stats;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Actions\Admin\Stats\ShowStatsAction;
use Modules\Auth\Transformers\Admin\Stats\StatsResource;

class ShowStatsController extends Controller
{
    public function __construct(protected ShowStatsAction $action) {}

    public function __invoke(Request $request): StatsResource
    {
        return new StatsResource($this->action->handle());
    }
}
