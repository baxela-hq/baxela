<?php

namespace Modules\Payment\Transformers\User\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Schemas\Payment\PaymentMethodEnum;

/**
 * @mixin PaymentMethodEnum
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->resource->value,
        ];
    }
}
