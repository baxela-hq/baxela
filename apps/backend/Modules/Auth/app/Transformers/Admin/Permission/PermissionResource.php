<?php

namespace Modules\Auth\Transformers\Admin\Permission;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Schemas\Permission\PermissionSchema;

/** @mixin \Modules\Auth\Models\Permission */
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            PermissionSchema::ID => $this->resource->getKey(),
            PermissionSchema::NAME => $this->resource->{PermissionSchema::NAME},
        ];
    }
}
