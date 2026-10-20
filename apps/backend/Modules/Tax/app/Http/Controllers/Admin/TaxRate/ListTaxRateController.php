<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxRate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tax\Actions\Admin\TaxRate\ListTaxRateAction;
use Modules\Tax\Transformers\Admin\TaxRate\TaxRateResource;

class ListTaxRateController extends Controller
{
    public function __construct(protected ListTaxRateAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return TaxRateResource::collection($this->action->handle());
    }
}
