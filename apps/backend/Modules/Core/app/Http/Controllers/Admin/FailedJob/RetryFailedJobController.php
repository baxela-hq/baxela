<?php

namespace Modules\Core\Http\Controllers\Admin\FailedJob;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Core\Actions\Admin\FailedJob\RetryFailedJobAction;

class RetryFailedJobController extends Controller
{
    public function __construct(protected RetryFailedJobAction $action) {}

    public function __invoke(string $uuid): Response
    {
        $this->action->handle($uuid);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
