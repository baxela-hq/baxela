<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @use ReadRepositoryTrait<TModel> */
    use ReadRepositoryTrait;

    /** @use WriteRepositoryTrait<TModel> */
    use WriteRepositoryTrait;

    /** @var TModel */
    protected Model $model;

    /** @param  TModel  $model */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}
