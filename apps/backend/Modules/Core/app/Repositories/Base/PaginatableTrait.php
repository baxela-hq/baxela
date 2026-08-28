<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait PaginatableTrait
{
    // Assumes $this->model exists (which it does in BaseRepository)

    public function allPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    public function findByPaginated(array $criteria, ?array $orderBy = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        foreach ($criteria as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        if ($orderBy) {
            foreach ($orderBy as $column => $direction) {
                $query->orderBy($column, $direction);
            }
        }

        return $query->paginate($perPage);
    }
}
