<?php

namespace Modules\Tenant\Http\Controllers\Admin\Membership;

use App\Http\Controllers\Controller;
use Modules\Tenant\Actions\Admin\Membership\TransferOwnershipAction;
use Modules\Tenant\Transformers\Admin\Membership\MembershipResource;

class TransferOwnershipController extends Controller
{
    public function __construct(protected TransferOwnershipAction $action) {}

    public function __invoke(string $id): MembershipResource
    {
        return MembershipResource::make($this->action->handle($id));
    }
}
