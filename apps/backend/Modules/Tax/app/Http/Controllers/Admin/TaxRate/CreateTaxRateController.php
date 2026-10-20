<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxRate;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxRate\CreateTaxRateAction;
use Modules\Tax\Http\Requests\Admin\TaxRate\TaxRateRequest;
use Modules\Tax\Transformers\Admin\TaxRate\TaxRateResource;

class CreateTaxRateController extends Controller
{
    public function __construct(protected CreateTaxRateAction $action) {}

    public function __invoke(TaxRateRequest $request): TaxRateResource
    {
        return TaxRateResource::make($this->action->handle($request->validated()));
    }
}
