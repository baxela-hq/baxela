<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Database\Eloquent\Model;

interface WriteRepositoryInterface
{
    public function create(array $data): Model;

    public function update(mixed $id, array $data): bool;

    public function delete(mixed $id): bool;
}
