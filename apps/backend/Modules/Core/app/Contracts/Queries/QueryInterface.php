<?php

namespace Modules\Core\Contracts\Queries;

use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\QueryBuilder;

interface QueryInterface
{
    public function build(): QueryBuilder;

    public function paginate(int $perPage = 15);

    public function get(): Collection;
}
