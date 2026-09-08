<?php

namespace Modules\Auth\Transformers\Admin\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Auth\Transformers\Admin\Role\RoleResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            UserSchema::ID => $this->resource->{UserSchema::ID},
            UserSchema::EMAIL => $this->resource->{UserSchema::EMAIL},
            UserSchema::EMAIL_VERIFIED_AT => $this->resource->{UserSchema::EMAIL_VERIFIED_AT},
            UserSchema::IS_ACTIVE => $this->resource->{UserSchema::IS_ACTIVE},
            UserSchema::COMMENT => $this->resource->{UserSchema::COMMENT},
            UserSchema::CREATED_AT => $this->resource->{UserSchema::CREATED_AT},
            UserSchema::UPDATED_AT => $this->resource->{UserSchema::UPDATED_AT},
            UserSchema::ROLES => RoleResource::collection($this->resource->roles),
        ];
    }
}
