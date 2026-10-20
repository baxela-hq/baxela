<?php

namespace Modules\Tax\Http\Controllers\Admin\TaxClass;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Tax\Actions\Admin\TaxClass\ListTaxClassAction;
use Modules\Tax\Transformers\Admin\TaxClass\TaxClassResource;

class ListTaxClassController extends Controller
{
    public function __construct(protected ListTaxClassAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return TaxClassResource::collection($this->action->handle());
    }
}
