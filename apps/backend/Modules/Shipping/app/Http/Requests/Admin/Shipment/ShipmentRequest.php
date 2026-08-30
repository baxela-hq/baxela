<?php

namespace Modules\Shipping\Http\Requests\Admin\Shipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Modules\Shipping\Schemas\Shipment\ShipmentSchema;
use Modules\Shipping\Schemas\Shipment\ShipmentStatusEnum;

class ShipmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            ShipmentSchema::ORDER_ID => ['required', 'integer'],
            ShipmentSchema::CARRIER_NAME => ['nullable', 'string', 'max:255'],
            ShipmentSchema::TRACKING_NUMBER => ['nullable', 'string', 'max:255'],
            ShipmentSchema::TRACKING_URL => ['nullable', 'url', 'max:255'],
            ShipmentSchema::NOTES => ['nullable', 'string'],
            ShipmentSchema::STATUS => ['nullable', new Enum(ShipmentStatusEnum::class)],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
