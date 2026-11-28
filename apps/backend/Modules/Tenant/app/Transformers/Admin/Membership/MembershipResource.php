<?php

namespace Modules\Tenant\Transformers\Admin\Membership;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A member of the active store, shaped by the listing action (§10 row 104). */
class MembershipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'user_id' => $this->resource['user_id'],
            'email' => $this->resource['email'],
            'status' => $this->resource['status'],
            'is_owner' => $this->resource['is_owner'],
            'joined_at' => $this->resource['joined_at'],
        ];
    }
}
