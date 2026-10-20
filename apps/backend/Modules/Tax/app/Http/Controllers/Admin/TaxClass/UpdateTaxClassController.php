<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxClass;

use App\Http\Controllers\Controller;
use Modules\Tax\Actions\Admin\TaxClass\UpdateTaxClassAction;
use Modules\Tax\Http\Requests\Admin\TaxClass\TaxClassRequest;
use Modules\Tax\Transformers\Admin\TaxClass\TaxClassResource;

class UpdateTaxClassController extends Controller
{
    public function __construct(protected UpdateTaxClassAction $action) {}

    public function __invoke(string $id, TaxClassRequest $request): TaxClassResource
    {
        return TaxClassResource::make($this->action->handle($id, $request->validated()));
    }
}
