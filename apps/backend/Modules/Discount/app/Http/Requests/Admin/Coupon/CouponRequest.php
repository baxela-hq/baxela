<?php

namespace Modules\Discount\Http\Requests\Admin\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Discount\Schemas\Coupon\CouponSchema;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;

class CouponRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Codes are stored uppercase-trimmed; normalize before the unique
        // check so the rule and the write agree
        if ($this->has(CouponSchema::CODE)) {
            $this->merge([
                CouponSchema::CODE => strtoupper(trim((string) $this->input(CouponSchema::CODE))),
            ]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $isPercent = $this->input(CouponSchema::TYPE) === CouponTypeEnum::PERCENT->value;

        return [
            CouponSchema::CODE => [
                'required', 'string', 'max:64', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique(CouponSchema::TABLE, CouponSchema::CODE)->ignore($id),
            ],
            CouponSchema::NAME => ['nullable', 'string', 'max:255'],
            CouponSchema::TYPE => ['required', Rule::enum(CouponTypeEnum::class)],
            // percent: 15.00 == 15%; fixed: major-unit amount off the subtotal
            CouponSchema::VALUE => ['required', 'numeric', 'gt:0', $isPercent ? 'max:100' : 'max:99999999.99'],
            CouponSchema::MAX_DISCOUNT_AMOUNT => [
                'nullable', 'numeric', 'gt:0', 'max:99999999.99',
                Rule::prohibitedIf(! $isPercent),
            ],
            CouponSchema::MIN_ORDER_AMOUNT => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            // Validity window in UTC (labels in the admin say so)
            CouponSchema::STARTS_AT => ['nullable', 'date'],
            CouponSchema::ENDS_AT => [
                'nullable', 'date',
                Rule::when($this->filled(CouponSchema::STARTS_AT),
                    ['after:'.(string) $this->input(CouponSchema::STARTS_AT)]),
            ],
            CouponSchema::USAGE_LIMIT => ['nullable', 'integer', 'gt:0', 'max:4294967295'],
            CouponSchema::PER_USER_LIMIT => ['nullable', 'integer', 'gt:0', 'max:4294967295'],
            CouponSchema::IS_ACTIVE => ['nullable', 'boolean'],
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
