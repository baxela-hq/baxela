<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaginatableInterface
{
    public function allPaginated(int $perPage = 15): LengthAwarePaginator;

    public function findByPaginated(
        array $criteria,
        ?array $orderBy = null,
        int $perPage = 15
    ): LengthAwarePaginator;
}
