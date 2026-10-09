<?php

namespace Modules\Discount\Http\Requests\Admin\Promotion;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Discount\Schemas\Coupon\CouponTypeEnum;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;

class PromotionRequest extends FormRequest
{
    public function rules(): array
    {
        $isPercent = $this->input(PromotionSchema::TYPE) === CouponTypeEnum::PERCENT->value;
        $isAll = $this->input(PromotionSchema::SCOPE) === ScopeTypeEnum::ALL->value;

        return [
            PromotionSchema::NAME => ['required', 'string', 'max:255'],
            PromotionSchema::SCOPE => ['required', Rule::enum(ScopeTypeEnum::class)],
            PromotionSchema::TYPE => ['required', Rule::enum(CouponTypeEnum::class)],
            // percent: 15.00 == 15%; fixed: major-unit amount off each unit
            PromotionSchema::VALUE => ['required', 'numeric', 'gt:0', $isPercent ? 'max:100' : 'max:99999999.99'],
            // Validity window in UTC (labels in the admin say so)
            PromotionSchema::STARTS_AT => ['nullable', 'date'],
            PromotionSchema::ENDS_AT => [
                'nullable', 'date',
                Rule::when($this->filled(PromotionSchema::STARTS_AT),
                    ['after:'.(string) $this->input(PromotionSchema::STARTS_AT)]),
            ],
            PromotionSchema::PRIORITY => ['nullable', 'integer', 'min:0', 'max:65535'],
            PromotionSchema::IS_ACTIVE => ['nullable', 'boolean'],
            // scope=all must stay selection-free — an id sent alongside it
            // is rejected so a store-wide promotion can never be widened by
            // accident; the ids' existence is checked through the Catalog
            // gateway, never by touching Catalog's tables
            PromotionSchema::PRODUCT_IDS => [
                'nullable', 'array', 'max:1000',
                Rule::prohibitedIf($isAll),
            ],
            PromotionSchema::PRODUCT_IDS.'.*' => [
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! app(CatalogGatewayInterface::class)->productExists((int) $value)) {
                        $fail('The product does not exist.');
                    }
                },
            ],
            PromotionSchema::CATEGORY_IDS => [
                'nullable', 'array', 'max:1000',
                Rule::prohibitedIf($isAll),
            ],
            PromotionSchema::CATEGORY_IDS.'.*' => [
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! app(CatalogGatewayInterface::class)->categoryExists((int) $value)) {
                        $fail('The category does not exist.');
                    }
                },
            ],
        ];
    }

    /**
     * scope=specific must carry at least one selection — an empty specific
     * scope is rejected instead of silently degrading to store-wide.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input(PromotionSchema::SCOPE) !== ScopeTypeEnum::SPECIFIC->value) {
                return;
            }

            $products = array_filter((array) $this->input(PromotionSchema::PRODUCT_IDS, []));
            $categories = array_filter((array) $this->input(PromotionSchema::CATEGORY_IDS, []));

            if ($products === [] && $categories === []) {
                $validator->errors()->add(
                    PromotionSchema::SCOPE,
                    'A specific promotion must select at least one product or category.'
                );
            }
        });
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
