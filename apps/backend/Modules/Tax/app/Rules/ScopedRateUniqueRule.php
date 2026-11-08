<?php

namespace Modules\Tax\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

/**
 * One rate per (country, region, class) — IMPLICIT because '' (the
 * country-wide scope) must be uniqueness-checked too, while ordinary
 * rules are skipped for empty strings (gap-C1a D1).
 */
class ScopedRateUniqueRule implements ImplicitRule
{
    public function __construct(
        private string $country,
        private int $taxClassId,
        private ?int $ignoreId,
    ) {}

    /**
     * The legacy Rule contract — ImplicitRule extends it; the validator
     * calls passes() for rule objects that are not ValidationRule.
     */
    public function passes($attribute, $value): bool
    {
        $region = strtoupper(trim((string) $value)) ?: '';

        return ! TaxRate::query()
            ->where(TaxRateSchema::COUNTRY_CODE, $this->country)
            ->where(TaxRateSchema::REGION_CODE, $region)
            ->where(TaxRateSchema::TAX_CLASS_ID, $this->taxClassId)
            ->when($this->ignoreId !== null, fn ($query) => $query->where(TaxRateSchema::ID, '!=', $this->ignoreId))
            ->exists();
    }

    public function message(): string
    {
        return __('validation.unique');
    }
}
