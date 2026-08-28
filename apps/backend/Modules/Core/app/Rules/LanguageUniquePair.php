<?php

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;

readonly class LanguageUniquePair implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected string $column,
        protected array $languageMap,
        private ?string $ignoreId = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $languageId = request()->input(str_replace('slug', 'language_id', $attribute));
        if (is_null($languageId)) {
            $fail(__('validation.unique'));

            return;
        }

        $query = DB::table($this->table)
            ->where([
                $this->column => $value,
                'language_id' => $languageId,
            ]);

        if ($this->ignoreId) {
            $query->where($this->column, '!=', $value);
        }

        if ($query->exists()) {
            $fail(__('validation.unique'));
        }
    }
}
