<?php

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Core\Models\Region;
use Modules\Core\Schemas\Region\RegionSchema;

/**
 * Validates a country+region PAIR (gap-C1a D4): the region must exist in
 * core_regions under the given country. Pass the country via the rule's
 * constructor — it is read from the validated country_code field.
 */
readonly class RegionCodeRule implements ValidationRule
{
    public function __construct(private string $countryCode) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return; // nullable/empty handled by the field's own rules
        }

        $exists = Region::query()
            ->where(RegionSchema::CODE, strtoupper(trim($value)))
            ->where(RegionSchema::COUNTRY_CODE, $this->countryCode)
            ->exists();

        if (! $exists) {
            $fail(__('validation.exists'));
        }
    }
}
