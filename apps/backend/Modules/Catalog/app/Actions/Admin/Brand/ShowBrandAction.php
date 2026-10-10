<?php

namespace Modules\Catalog\Actions\Admin\Brand;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Schemas\Brand\BrandSchema;

class ShowBrandAction extends AbstractBrandAction
{
    public function handle(string $id): Model
    {
        return $this->model->query()->with(BrandSchema::RES_TRANSLATIONS)->findOrFail($id);
    }
}
