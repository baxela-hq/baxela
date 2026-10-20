<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxClass;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxClass\ShowTaxClassAction;
use Modules\Tax\Transformers\Admin\TaxClass\TaxClassResource;

class ShowTaxClassController extends Controller
{
    public function __construct(protected ShowTaxClassAction $action) {}

    public function __invoke(string $id): TaxClassResource
    {
        return TaxClassResource::make($this->action->handle($id));
    }
}
