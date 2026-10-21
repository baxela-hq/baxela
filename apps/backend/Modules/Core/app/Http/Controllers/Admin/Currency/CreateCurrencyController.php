<?php

namespace Modules\Core\Http\Controllers\Admin\Currency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Actions\Admin\Currency\CreateCurrencyAction;
use Modules\Core\Http\Requests\Admin\Currency\StoreCurrencyRequest;
use Modules\Core\Transformers\Admin\Currency\CurrencyResource;

class CreateCurrencyController extends Controller
{
    public function __construct(protected CreateCurrencyAction $action) {}

    public function __invoke(StoreCurrencyRequest $request): JsonResource
    {
        return CurrencyResource::make($this->action->handle($request->validated()));
    }
}
