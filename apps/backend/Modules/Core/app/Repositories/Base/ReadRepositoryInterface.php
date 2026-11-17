<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface ReadRepositoryInterface
{
    /** @return Collection<int, Model> */
    public function all(): Collection;

    public function find(mixed $id): ?Model;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string>|null  $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?Model;

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, string>|null  $orderBy
     * @return Collection<int, Model>
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): Collection;
}
