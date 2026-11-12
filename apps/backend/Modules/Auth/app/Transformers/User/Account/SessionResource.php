<?php

namespace Modules\Auth\Transformers\User\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Schemas\Token\PersonalAccessTokenSchema;

/** @mixin PersonalAccessToken */
class SessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // currentAccessToken() is only a PersonalAccessToken when the
        // request carries a real bearer token; other auth (actingAs in
        // tests, future guards) yields a TransientToken with no id —
        // nothing is "current" then.
        $current = $request->user()?->currentAccessToken();

        $currentId = $current instanceof PersonalAccessToken
            ? $current->{PersonalAccessTokenSchema::ID}
            : null;

        return [
            PersonalAccessTokenSchema::ID => $this->resource->{PersonalAccessTokenSchema::ID},
            PersonalAccessTokenSchema::NAME => $this->resource->{PersonalAccessTokenSchema::NAME},
            PersonalAccessTokenSchema::ABILITIES => $this->resource->{PersonalAccessTokenSchema::ABILITIES},
            PersonalAccessTokenSchema::CREATED_AT => $this->resource->{PersonalAccessTokenSchema::CREATED_AT}?->toIso8601String(),
            PersonalAccessTokenSchema::LAST_USED_AT => $this->resource->{PersonalAccessTokenSchema::LAST_USED_AT}?->toIso8601String(),
            PersonalAccessTokenSchema::EXPIRES_AT => $this->resource->{PersonalAccessTokenSchema::EXPIRES_AT}?->toIso8601String(),
            PersonalAccessTokenSchema::IS_CURRENT => $currentId === $this->resource->{PersonalAccessTokenSchema::ID},
        ];
    }
}
