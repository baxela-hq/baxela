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
 * Tenant-aware replacement for `Rule::exists` on store-owned tables
 * (multi-tenancy §4.3, layer 1). Laravel's database rules go through the
 * query builder, which never sees the Eloquent tenant scope; this rule adds
 * the active tenant predicate itself and fails closed outside MODE_TENANT.
 */
final class ScopedExists implements ValidationRule
{
    /**
     * @param  array<string, mixed>  $conditions  extra predicates; a null value becomes IS NULL
     */
    public function __construct(
        protected string $table,
        protected string $column = PrimaryKeyColumn::ID,
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

        if (! $query->exists()) {
            $fail(__('validation.exists'));
        }
    }
}
