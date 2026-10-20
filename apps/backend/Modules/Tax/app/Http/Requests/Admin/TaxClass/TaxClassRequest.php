<?php

namespace Modules\Tax\Http\Requests\Admin\TaxClass;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;

class TaxClassRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            TaxClassSchema::NAME => ['required', 'string', 'max:255'],
            TaxClassSchema::IS_DEFAULT => ['required', 'boolean'],
            TaxClassSchema::IS_SHIPPING => ['required', 'boolean'],
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
