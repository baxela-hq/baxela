<?php

namespace Modules\Tenant\Http\Controllers\Admin\Membership;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Actions\Admin\Membership\InviteMemberAction;
use Modules\Tenant\Http\Requests\Admin\Membership\InviteMemberRequest;
use Modules\Tenant\Transformers\Admin\Membership\MembershipResource;

class InviteMemberController extends Controller
{
    public function __construct(protected InviteMemberAction $action) {}

    public function __invoke(InviteMemberRequest $request): JsonResponse
    {
        // The action returns the member shape (§10 row 104 discipline);
        // the create answers 201 explicitly.
        return MembershipResource::make(
            $this->action->handle((string) $request->validated('email')),
        )->response()->setStatusCode(201);
    }
}
