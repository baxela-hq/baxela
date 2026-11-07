<?php

namespace Modules\Core\Http\Controllers\Admin\FailedJob;

use App\Http\Controllers\Controller;
use Modules\Core\Actions\Admin\FailedJob\ShowFailedJobAction;
use Modules\Core\Transformers\Admin\FailedJob\FailedJobResource;

class ShowFailedJobController extends Controller
{
    public function __construct(protected ShowFailedJobAction $action) {}

    public function __invoke(string $uuid): FailedJobResource
    {
        return new FailedJobResource($this->action->handle($uuid), detailed: true);
    }
}
