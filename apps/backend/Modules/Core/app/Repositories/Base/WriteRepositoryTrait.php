<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Database\Eloquent\Model;

trait WriteRepositoryTrait
{
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(mixed $id, array $data): bool
    {
        $model = $this->find($id); // Relies on the class having a 'find' method

        return $model && $model->update($data);
    }

    public function delete(mixed $id): bool
    {
        return $this->model->destroy($id) > 0;
    }
}
