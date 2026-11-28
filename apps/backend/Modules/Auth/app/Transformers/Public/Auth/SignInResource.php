<?php

namespace Modules\Auth\Transformers\Public\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Transformers\Public\User\UserResource;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Tenant\TenantGatewayInterface;

class SignInResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $coreGateway = app(CoreGatewayInterface::class);
        $tenants = app(TenantGatewayInterface::class);

        // Store memberships travel with the sign-in response (multi-tenancy
        // §5, from tenant_memberships — never from roles): the admin panel
        // shows the switcher from this list (A5) and sends X-Tenant-Id per
        // request. With exactly one membership the store is also named so
        // a single-store deployment needs no selection round-trip. Sanctum
        // tokens stay global user credentials; tenant selection is per
        // request (F23).
        $memberships = $tenants->membershipsFor((int) $this->resource->user->getKey());
        $activeTenant = count($memberships) === 1 ? $memberships[0] : null;

        return [
            'token' => $this->resource->token,
            'user' => UserResource::make($this->resource->user),
            'memberships' => $memberships,
            'active_tenant' => $activeTenant,
            'settings' => [
                'currency' => $coreGateway->getDefaultCurrency(),
                'language' => $coreGateway->getDefaultLanguage(),
            ],
        ];
    }
}
