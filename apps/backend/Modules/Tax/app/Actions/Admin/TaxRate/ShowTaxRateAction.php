<?php

namespace Modules\Tax\Actions\Admin\TaxRate;

use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

class ShowTaxRateAction
{
    public function __construct(protected TaxRate $model) {}

    public function handle(string $id): TaxRate
    {
        return $this->model->query()->with(TaxRateSchema::RELATION_TAX_CLASS)->findOrFail($id);
    }
}
