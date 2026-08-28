<?php

namespace Modules\Auth\Transformers\Public\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Transformers\Public\User\UserResource;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;

class SignInResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $coreGateway = app(CoreGatewayInterface::class);

        return [
            'token' => $this->resource->token,
            'user' => UserResource::make($this->resource->user),
            'settings' => [
                'currency' => $coreGateway->getDefaultCurrency(),
                'language' => $coreGateway->getDefaultLanguage(),
            ],
        ];
    }
}
