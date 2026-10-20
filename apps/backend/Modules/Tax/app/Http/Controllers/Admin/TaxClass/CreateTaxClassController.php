<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxClass;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxClass\CreateTaxClassAction;
use Modules\Tax\Http\Requests\Admin\TaxClass\TaxClassRequest;
use Modules\Tax\Transformers\Admin\TaxClass\TaxClassResource;

class CreateTaxClassController extends Controller
{
    public function __construct(protected CreateTaxClassAction $action) {}

    public function __invoke(TaxClassRequest $request): TaxClassResource
    {
        return TaxClassResource::make($this->action->handle($request->validated()));
    }
}
