<?php

namespace Modules\Core\Http\Controllers\Admin\Currency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Actions\Admin\Currency\UpdateCurrencyAction;
use Modules\Core\Http\Requests\Admin\Currency\UpdateCurrencyRequest;
use Modules\Core\Transformers\Admin\Currency\CurrencyResource;

class UpdateCurrencyController extends Controller
{
    public function __construct(protected UpdateCurrencyAction $action) {}

    public function __invoke(UpdateCurrencyRequest $request, string $id): JsonResource
    {
        return CurrencyResource::make($this->action->handle($id, $request->validated()));
    }
}
