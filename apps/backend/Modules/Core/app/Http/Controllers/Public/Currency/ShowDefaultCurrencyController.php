<?php

namespace Modules\Core\Http\Controllers\Public\Currency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Actions\Public\Currency\ShowDefaultCurrencyAction;
use Modules\Core\Transformers\Public\Currency\CurrencyResource;

class ShowDefaultCurrencyController extends Controller
{
    public function __construct(protected ShowDefaultCurrencyAction $action) {}

    public function __invoke(Request $request): JsonResource
    {
        $currency = $this->action->handle();

        abort_if(is_null($currency), 404);

        return new CurrencyResource($currency);
    }
}
