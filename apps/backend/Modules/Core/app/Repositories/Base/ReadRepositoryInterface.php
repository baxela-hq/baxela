<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ReadRepositoryInterface
{
    public function all(): Collection;

    public function find(mixed $id): ?Model;

    public function findOneBy(array $criteria, ?array $orderBy = null): ?Model;

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): Collection;
}
