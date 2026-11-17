<?php

namespace Modules\Payment\Transformers\User\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Models\Payment;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->resource->payment_id,
            'payment_url' => $this->resource->payment_url,
        ];
    }
}
