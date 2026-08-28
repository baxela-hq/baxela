<?php

namespace Modules\Media\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class NullableExactFilter implements Filter
{
    public function __construct(protected string $column) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $values = is_array($value) ? $value : [$value];

        $nullValues = array_filter($values, fn ($v) => $this->isNullValue($v));
        $nonNullValues = array_filter($values, fn ($v) => ! $this->isNullValue($v));

        if ($nullValues !== []) {
            $query->whereNull($this->column);
        }

        if ($nonNullValues !== []) {
            $query->whereIn($this->column, array_map(fn ($v) => (int) $v, $nonNullValues));
        }
    }

    private function isNullValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === 'null';
    }
}
