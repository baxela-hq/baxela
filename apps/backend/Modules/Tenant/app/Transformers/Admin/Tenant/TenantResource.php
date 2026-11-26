<?php

namespace Modules\Tenant\Transformers\Admin\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Transformers\Admin\TenantDomain\TenantDomainResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            TenantSchema::ID => $this->{TenantSchema::ID},
            TenantSchema::NAME => $this->{TenantSchema::NAME},
            TenantSchema::SLUG => $this->{TenantSchema::SLUG},
            TenantSchema::EMAIL => $this->{TenantSchema::EMAIL},
            TenantSchema::STATUS => $this->{TenantSchema::STATUS},
            TenantSchema::CREATED_AT => $this->{TenantSchema::CREATED_AT},
            TenantSchema::UPDATED_AT => $this->{TenantSchema::UPDATED_AT},
            TenantSchema::RES_DOMAINS => $this->whenLoaded(
                'domains',
                fn () => TenantDomainResource::collection($this->resource->domains),
            ),
        ];
    }
}
