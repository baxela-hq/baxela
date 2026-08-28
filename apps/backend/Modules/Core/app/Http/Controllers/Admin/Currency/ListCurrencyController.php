<?php

namespace Modules\Core\Http\Controllers\Admin\Currency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Admin\Currency\ListCurrencyAction;
use Modules\Core\Transformers\Admin\Currency\CurrencyResource;

class ListCurrencyController extends Controller
{
    public function __construct(protected ListCurrencyAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return CurrencyResource::collection($this->action->handle());
    }
}
