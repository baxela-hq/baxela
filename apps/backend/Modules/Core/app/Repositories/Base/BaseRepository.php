<?php

namespace Modules\Core\Repositories\Base;

use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    use ReadRepositoryTrait;
    use WriteRepositoryTrait;

    public function __construct(Model $model) {}
}
