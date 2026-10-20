<?php

namespace Modules\Cart\Http\Requests\User\Tax;

use Illuminate\Foundation\Http\FormRequest;

class TaxPreviewRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer'],
            'shipping_method_id' => ['nullable', 'integer'],
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
