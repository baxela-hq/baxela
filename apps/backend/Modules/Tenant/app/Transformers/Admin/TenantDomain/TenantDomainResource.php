<?php

namespace Modules\Tenant\Transformers\Admin\TenantDomain;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;

/** @mixin TenantDomain */
class TenantDomainResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            TenantDomainSchema::ID => $this->{TenantDomainSchema::ID},
            TenantDomainSchema::TENANT_ID => $this->{TenantDomainSchema::TENANT_ID},
            TenantDomainSchema::DOMAIN => $this->{TenantDomainSchema::DOMAIN},
            TenantDomainSchema::STATUS => $this->{TenantDomainSchema::STATUS},
            TenantDomainSchema::IS_PRIMARY => $this->{TenantDomainSchema::IS_PRIMARY},
            TenantDomainSchema::VERIFIED_AT => $this->{TenantDomainSchema::VERIFIED_AT},
            TenantDomainSchema::VERIFICATION_METHOD => $this->{TenantDomainSchema::VERIFICATION_METHOD},
            TenantDomainSchema::VERIFICATION_EXPIRES_AT => $this->{TenantDomainSchema::VERIFICATION_EXPIRES_AT},
            TenantDomainSchema::RELEASED_AT => $this->{TenantDomainSchema::RELEASED_AT},
            TenantDomainSchema::COOLING_OFF_UNTIL => $this->{TenantDomainSchema::COOLING_OFF_UNTIL},
        ];
    }
}
