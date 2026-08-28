<?php

namespace Modules\Auth\Transformers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DefaultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource->message ?? 'Request Processed Successfully',
        ];
    }
}
