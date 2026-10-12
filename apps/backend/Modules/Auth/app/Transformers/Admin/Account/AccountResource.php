<?php

namespace Modules\Auth\Transformers\Admin\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\Auth\Models\Role;
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
            UserSchema::ID => $this->resource->{UserSchema::ID},
            UserSchema::EMAIL => $this->resource->{UserSchema::EMAIL},
            UserSchema::ROLES => RoleResource::collection($this->resource->roles),
            UserSchema::PERMISSIONS => $this->permissions(),
        ];
    }

    /**
     * The admin UI's wildcard: super-admins bypass permission checks, so they
     * are reported as holding every permission rather than an empty list.
     *
     * @return Collection<int, string>
     */
    protected function permissions(): Collection
    {
        if ($this->resource->hasRole(Role::SUPER_ADMIN)) {
            return collect(['*']);
        }

        return $this->resource->getAllPermissions()->pluck('name');
    }
}
