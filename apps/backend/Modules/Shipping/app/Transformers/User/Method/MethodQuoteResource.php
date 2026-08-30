<?php

namespace Modules\Shipping\Transformers\User\Method;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Contracts\Gateways\Shipping\DTOs\ShippingMethodQuoteDto;

class MethodQuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var ShippingMethodQuoteDto $quote */
        $quote = $this->resource;

        return [
            'id' => $quote->id,
            'name' => $quote->name,
            'price' => $quote->price,
        ];
    }
}
