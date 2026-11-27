<?php

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\PotentiallyTranslatedString;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Schemas\Shared\PrimaryKeyColumn;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;

readonly class LanguageUniquePair implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected string $column,
        protected array $languageMap,
        private ?string $ignoreId = null,
        private ?string $ignoreColumn = null
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

        /** @var TenantContext $context */
        $context = app(TenantContext::class);
        if ($context->mode() !== TenantContext::MODE_TENANT) {
            throw new TenantContextMissingException;
        }
        $query->where(TenantColumn::TENANT_ID, $context->tenantId());

        if ($this->ignoreId !== null) {
            // Translation rows are frequently deleted and recreated on
            // update, so callers ignore the parent id in the translation's
            // foreign-key column, not its surrogate id.
            $query->where($this->ignoreColumn ?? PrimaryKeyColumn::ID, '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail(__('validation.unique'));
        }
    }
}
