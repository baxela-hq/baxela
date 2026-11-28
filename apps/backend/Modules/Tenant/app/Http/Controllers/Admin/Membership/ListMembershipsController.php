<?php

namespace Modules\Tenant\Http\Controllers\Admin\Membership;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tenant\Actions\Admin\Membership\ListMembershipsAction;
use Modules\Tenant\Transformers\Admin\Membership\MembershipResource;

class ListMembershipsController extends Controller
{
    public function __construct(protected ListMembershipsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return MembershipResource::collection($this->action->handle());
    }
}
