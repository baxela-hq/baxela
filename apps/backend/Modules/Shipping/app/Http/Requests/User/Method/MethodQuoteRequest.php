<?php

namespace Modules\Shipping\Http\Requests\User\Method;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MethodQuoteRequest extends FormRequest
{
    /** @return array<string, array<int, string|Enum|array<int, string>>> */
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
