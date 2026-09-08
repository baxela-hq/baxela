<?php

namespace Modules\Auth\Transformers\Admin\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Transformers\Admin\Role\RoleResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            UserSchema::EMAIL => $this->resource->{UserSchema::EMAIL},
            UserSchema::ROLES => RoleResource::collection($this->resource->roles),
        ];
    }
}
