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

class UniquePair implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected array $columns,
        private readonly ?int $ignoreId = null
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
        $query = DB::table($this->table)
            ->where($this->columns);

        /** @var TenantContext $context */
        $context = app(TenantContext::class);
        if ($context->mode() !== TenantContext::MODE_TENANT) {
            // A uniqueness rule must never fall back to an installation-wide
            // check — that would let store A see store B's rows (§4.3 L1).
            throw new TenantContextMissingException;
        }
        $query->where(TenantColumn::TENANT_ID, $context->tenantId());

        if ($this->ignoreId) {
            $query->where(PrimaryKeyColumn::ID, '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail(__('validation.unique'));
        }
    }
}
