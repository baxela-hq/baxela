<?php

namespace Modules\Cart\Http\Requests\User\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CheckoutRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    /** @return array<string, array<int, string|array<int, string>|Enum>> */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer'],
            'shipping_method_id' => ['nullable', 'integer'],
            // Display-only preference: resolved and rate-snapshotted
            // server-side in CheckoutAction, never trusted for amounts.
            'display_currency' => ['nullable', 'string', 'size:3'],
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
