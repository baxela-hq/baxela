<?php

namespace Modules\Payment\Transformers\Admin\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Gateways\PaymentDriverManager;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

/**
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $manager = app(PaymentDriverManager::class);
        $method = $this->resource->{PaymentMethodSchema::METHOD};

        return [
            PaymentMethodSchema::ID => $this->resource->{PaymentMethodSchema::ID},
            PaymentMethodSchema::METHOD => $method,
            PaymentMethodSchema::IS_ACTIVE => $this->resource->{PaymentMethodSchema::IS_ACTIVE},
            PaymentMethodSchema::SORT_ORDER => $this->resource->{PaymentMethodSchema::SORT_ORDER},
            PaymentMethodSchema::IS_CONFIGURED => $manager->isRegistered($method) && $manager->isConfigured($method),
            PaymentMethodSchema::IS_REGISTERED => $manager->isRegistered($method),
        ];
    }
}
