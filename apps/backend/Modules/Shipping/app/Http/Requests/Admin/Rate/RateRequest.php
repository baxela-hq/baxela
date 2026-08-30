<?php

namespace Modules\Shipping\Http\Requests\Admin\Rate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Rules\UniquePair;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

class RateRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            RateSchema::METHOD_ID => ['required', 'integer',
                Rule::exists(MethodSchema::TABLE, MethodSchema::ID)],
            RateSchema::ZONE_ID => ['required', 'integer',
                Rule::exists(ZoneSchema::TABLE, ZoneSchema::ID)],
            RateSchema::PRICE => ['required', 'numeric', 'min:0',
                new UniquePair(RateSchema::TABLE, [
                    RateSchema::METHOD_ID => $this->input(RateSchema::METHOD_ID),
                    RateSchema::ZONE_ID => $this->input(RateSchema::ZONE_ID),
                ], is_null($id) ? null : (int) $id)],
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
