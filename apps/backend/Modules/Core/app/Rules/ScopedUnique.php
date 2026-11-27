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
 * Tenant-scoped replacement for the single-column `Rule::unique` form
 * (multi-tenancy §4.3, layer 1). Composite keys keep using UniquePair.
 */
final class ScopedUnique implements ValidationRule
{
    /**
     * @param  array<string, mixed>  $conditions  extra predicates; a null value becomes IS NULL
     */
    public function __construct(
        protected string $table,
        protected string $column,
        protected ?int $ignoreId = null,
        protected string $ignoreColumn = PrimaryKeyColumn::ID,
        protected array $conditions = [],
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

        $query = DB::table($this->table)
            ->where(TenantColumn::TENANT_ID, $context->tenantId())
            ->where($this->column, $value);

        foreach ($this->conditions as $column => $condition) {
            if ($condition === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $condition);
            }
        }

        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail(__('validation.unique'));
        }
    }
}
