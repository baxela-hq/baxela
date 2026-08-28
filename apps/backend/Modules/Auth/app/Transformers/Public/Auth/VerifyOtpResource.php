<?php

namespace Modules\Auth\Transformers\Public\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Transformers\Public\User\UserResource;

class VerifyOtpResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->token,
            'user' => UserResource::make($this->resource->user),
        ];
    }
}
