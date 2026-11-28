<?php

namespace Modules\Tenant\Http\Controllers\Admin\Membership;

use App\Http\Controllers\Controller;
use Modules\Tenant\Actions\Admin\Membership\SetMemberStatusAction;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Transformers\Admin\Membership\MembershipResource;

class SuspendMemberController extends Controller
{
    public function __construct(protected SetMemberStatusAction $action) {}

    public function __invoke(string $id): MembershipResource
    {
        return MembershipResource::make(
            $this->action->handle($id, TenantMembershipSchema::STATUS_SUSPENDED),
        );
    }
}
