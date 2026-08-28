<?php

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Language\LanguageSchema;

readonly class LanguageRule implements ValidationRule
{
    public function __construct(?string $attr = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rules = ['translations.*.language' => ['string', 'distinct', 'size:2']];
        $input = [
            'translations' => [
                ['language' => $value],
            ],
        ];
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            $fail($validator->errors()->first());

            return;
        }

        $query = Language::query()->where([
            LanguageSchema::CODE => $value,
            LanguageSchema::IS_ACTIVE => true,
        ]);

        if (! $query->exists()) {
            $fail(__('validation.exists'));
        }
    }
}
