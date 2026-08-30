<?php

namespace Modules\Shipping\Actions\Admin\Rate;

use Illuminate\Database\Eloquent\Model;
use Modules\Shipping\Models\Rate;

class ShowRateAction
{
    public function __construct(protected Rate $model) {}

    public function handle(string $id): Model
    {
        return $this->model->query()->findOrFail($id);
    }
}
