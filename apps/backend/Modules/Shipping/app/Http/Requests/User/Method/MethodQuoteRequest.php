<?php

namespace Modules\Shipping\Http\Requests\User\Method;

use Illuminate\Foundation\Http\FormRequest;

class MethodQuoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer'],
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
