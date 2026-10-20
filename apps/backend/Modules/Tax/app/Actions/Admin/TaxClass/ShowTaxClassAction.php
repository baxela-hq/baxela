<?php

namespace Modules\Tax\Actions\Admin\TaxClass;

use Illuminate\Database\Eloquent\Model;
use Modules\Tax\Models\TaxClass;

class ShowTaxClassAction
{
    public function __construct(protected TaxClass $model) {}

    public function handle(string $id): Model
    {
        return $this->model->query()->findOrFail($id);
    }
}
