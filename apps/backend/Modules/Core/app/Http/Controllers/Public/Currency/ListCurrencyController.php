<?php

namespace Modules\Core\Http\Controllers\Public\Currency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Actions\Public\Currency\ListActiveCurrenciesAction;
use Modules\Core\Transformers\Public\Currency\ActiveCurrencyResource;

class ListCurrencyController extends Controller
{
    public function __construct(protected ListActiveCurrenciesAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return ActiveCurrencyResource::collection($this->action->handle());
    }
}
