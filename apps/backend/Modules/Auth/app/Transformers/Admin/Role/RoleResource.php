<?php

namespace Modules\Auth\Transformers\Admin\Role;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Schemas\Role\RoleSchema;
use Modules\Auth\Transformers\Admin\Permission\PermissionResource;

/** @mixin \Modules\Auth\Models\Role */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            RoleSchema::ID => $this->resource->getKey(),
            RoleSchema::NAME => $this->resource->{RoleSchema::NAME},
            RoleSchema::PERMISSIONS => PermissionResource::collection($this->resource->permissions),
        ];
    }
}
