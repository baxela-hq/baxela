<?php

namespace Modules\Shipping\Http\Controllers\User\Method;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shipping\Actions\User\Method\ListMethodAction;
use Modules\Shipping\Http\Requests\User\Method\MethodQuoteRequest;
use Modules\Shipping\Transformers\User\Method\MethodQuoteResource;

class ListMethodController extends Controller
{
    public function __construct(protected ListMethodAction $action) {}

    public function __invoke(MethodQuoteRequest $request): AnonymousResourceCollection
    {
        $quotes = $this->action->handle($request->input('address_id'));

        return MethodQuoteResource::collection($quotes);
    }
}
