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

/**
 * Tenant-scoped uniqueness for the functional `LOWER(column)` indexes
 * (multi-tenancy §4.5 class F). The compared column comes from a module
 * Schema constant; the value is normalized here so validation and storage
 * agree on every engine.
 */
final class LowercaseUniquePair implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected string $column,
        protected ?int $ignoreId = null,
        protected string $ignoreColumn = PrimaryKeyColumn::ID,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);
        if ($context->mode() !== TenantContext::MODE_TENANT) {
            throw new TenantContextMissingException;
        }

        $taken = DB::table($this->table)
            ->where(TenantColumn::TENANT_ID, $context->tenantId())
            ->whereRaw('LOWER('.$this->column.') = ?', [mb_strtolower(trim((string) $value))])
            ->when($this->ignoreId !== null, fn ($query) => $query->where($this->ignoreColumn, '!=', $this->ignoreId))
            ->exists();

        if ($taken) {
            $fail(__('validation.unique'));
        }
    }
}
