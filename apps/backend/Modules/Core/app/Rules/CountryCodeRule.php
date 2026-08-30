<?php

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Core\Models\Country;
use Modules\Core\Schemas\Country\CountrySchema;

readonly class CountryCodeRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) !== 2) {
            $fail(__('validation.size.string', ['size' => 2]));

            return;
        }

        $exists = Country::query()
            ->where(CountrySchema::CODE, $value)
            ->exists();

        if (! $exists) {
            $fail(__('validation.exists'));
        }
    }
}
