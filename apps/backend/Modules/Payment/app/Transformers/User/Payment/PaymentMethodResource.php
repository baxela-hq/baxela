<?php

namespace Modules\Payment\Transformers\User\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Models\PaymentMethod;

/** @mixin PaymentMethod */
class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->resource,
        ];
    }
}
