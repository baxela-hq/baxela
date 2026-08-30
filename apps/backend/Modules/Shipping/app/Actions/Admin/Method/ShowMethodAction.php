<?php

namespace Modules\Shipping\Actions\Admin\Method;

use Illuminate\Database\Eloquent\Model;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Schemas\Method\MethodSchema;

class ShowMethodAction
{
    public function __construct(protected Method $model) {}

    public function handle(string $id): Model
    {
        return $this->model->query()->with(MethodSchema::RES_TRANSLATIONS)->findOrFail($id);
    }
}
