<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait ReadRepositoryTrait
{
    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find(mixed $id): ?Model
    {
        return $this->model->find($id);
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?Model
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

        return $query->first();
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): Collection
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

        if ($limit) {
            $query->limit($limit);
        }
        if ($offset) {
            $query->offset($offset);
        }

        return $query->get();
    }
}
