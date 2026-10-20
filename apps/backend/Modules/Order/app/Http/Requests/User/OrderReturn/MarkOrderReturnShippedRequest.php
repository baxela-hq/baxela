<?php

namespace Modules\Order\Http\Requests\User\OrderReturn;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Order\Schemas\OrderReturn\OrderReturnSchema;

class MarkOrderReturnShippedRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            OrderReturnSchema::TRACKING_NUMBER => ['nullable', 'string', 'max:255'],
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
