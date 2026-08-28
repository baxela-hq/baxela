<?php

namespace Modules\Core\Contracts\Queries;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

trait QueryTrait
{
    public function get(): Collection
    {
        return $this->build()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator|AbstractPaginator
    {
        return $this->build()->paginate($perPage)->withQueryString();
    }
}
