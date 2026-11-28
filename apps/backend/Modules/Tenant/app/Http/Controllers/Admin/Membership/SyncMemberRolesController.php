<?php

namespace Modules\Tenant\Http\Controllers\Admin\Membership;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Actions\Admin\Membership\SyncMemberRolesAction;
use Modules\Tenant\Http\Requests\Admin\Membership\MemberRolesRequest;

class SyncMemberRolesController extends Controller
{
    public function __construct(protected SyncMemberRolesAction $action) {}

    public function __invoke(string $id, MemberRolesRequest $request): JsonResponse
    {
        $this->action->handle($id, (array) $request->validated('role_ids'));

        return response()->json(null, 204);
    }
}
