<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxRate;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxRate\ShowTaxRateAction;
use Modules\Tax\Transformers\Admin\TaxRate\TaxRateResource;

class ShowTaxRateController extends Controller
{
    public function __construct(protected ShowTaxRateAction $action) {}

    public function __invoke(string $id): TaxRateResource
    {
        return TaxRateResource::make($this->action->handle($id));
    }
}
