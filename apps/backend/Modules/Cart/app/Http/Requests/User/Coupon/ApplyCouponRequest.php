<?php

namespace Modules\Cart\Http\Requests\User\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ApplyCouponRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    /** @return array<string, array<int, string|array<int, string>|Enum>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
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
