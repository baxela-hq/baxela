<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxRate;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxRate\UpdateTaxRateAction;
use Modules\Tax\Http\Requests\Admin\TaxRate\TaxRateRequest;
use Modules\Tax\Transformers\Admin\TaxRate\TaxRateResource;

class UpdateTaxRateController extends Controller
{
    public function __construct(protected UpdateTaxRateAction $action) {}

    public function __invoke(string $id, TaxRateRequest $request): TaxRateResource
    {
        return TaxRateResource::make($this->action->handle($id, $request->validated()));
    }
}
