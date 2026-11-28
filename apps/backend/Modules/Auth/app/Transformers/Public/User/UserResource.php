<?php

namespace Modules\Auth\Transformers\Public\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Support\Tenancy\TenantContext;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            UserSchema::ID => $this->resource->{UserSchema::ID},
            UserSchema::EMAIL => $this->resource->{UserSchema::EMAIL},
            UserSchema::EMAIL_VERIFIED_AT => $this->resource->{UserSchema::EMAIL_VERIFIED_AT},
            UserSchema::IS_ACTIVE => $this->resource->{UserSchema::IS_ACTIVE},
            UserSchema::COMMENT => $this->resource->{UserSchema::COMMENT},
            // Tenant-scoped realtime clients need the store they are
            // browsing/administering to build their private channel name.
            UserSchema::TENANT_ID => app(TenantContext::class)->tenantId(),
        ];
    }
}
