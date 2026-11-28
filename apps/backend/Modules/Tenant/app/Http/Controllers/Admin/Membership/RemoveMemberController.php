<?php

namespace Modules\Tenant\Http\Controllers\Admin\Membership;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Actions\Admin\Membership\RemoveMemberAction;

class RemoveMemberController extends Controller
{
    public function __construct(protected RemoveMemberAction $action) {}

    public function __invoke(string $id): JsonResponse
    {
        $this->action->handle($id);

        return response()->json(null, 204);
    }
}
