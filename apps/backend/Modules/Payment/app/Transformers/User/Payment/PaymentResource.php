<?php

namespace Modules\Payment\Transformers\User\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_url' => $this->resource->payment_url,
        ];
    }
}
