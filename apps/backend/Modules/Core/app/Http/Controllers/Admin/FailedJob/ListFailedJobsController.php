<?php

namespace Modules\Core\Http\Controllers\Admin\FailedJob;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Admin\FailedJob\ListFailedJobsAction;
use Modules\Core\Transformers\Admin\FailedJob\FailedJobResource;

class ListFailedJobsController extends Controller
{
    public function __construct(protected ListFailedJobsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return FailedJobResource::collection($this->action->handle());
    }
}
