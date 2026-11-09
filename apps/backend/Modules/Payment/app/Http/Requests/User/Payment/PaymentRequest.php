<?php

namespace Modules\Payment\Http\Requests\User\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;
use Modules\Payment\Schemas\Payment\PaymentSchema;

class PaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            PaymentSchema::REQ_ORDER_CODE => ['required', 'string', 'regex:/^[2-9A-HJKMNP-Z]{8}$/'],
            PaymentSchema::METHOD => ['required', 'string', 'max:255', Rule::in($this->acceptableMethods())],
        ];
    }

    /**
     * Slugs validation accepts: every registered driver — so a known
     * method missing its row fails at the action as method_inactive —
     * plus every stored row — so a removed extension's leftover row
     * fails as method_not_supported instead of an opaque 422. Anything
     * unknown to both layers is rejected here.
     *
     * @return list<string>
     */
    private function acceptableMethods(): array
    {
        $registered = array_keys((array) config('payment.drivers'));

        $stored = PaymentMethod::query()
            ->pluck(PaymentMethodSchema::METHOD)
            ->all();

        return array_values(array_unique([...$registered, ...$stored]));
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
