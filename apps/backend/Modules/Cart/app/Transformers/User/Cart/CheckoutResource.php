<?php

namespace Modules\Cart\Transformers\User\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->resource->order_id,
        ];
    }
}
