<?php

namespace Modules\Discount\Http\Controllers\Admin\Promotion;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Discount\Actions\Admin\Promotion\CreatePromotionAction;
use Modules\Discount\Http\Requests\Admin\Promotion\PromotionRequest;
use Modules\Discount\Transformers\Admin\Promotion\PromotionResource;

class CreatePromotionController extends Controller
{
    public function __construct(protected CreatePromotionAction $action) {}

    public function __invoke(PromotionRequest $request): JsonResponse
    {
        return PromotionResource::make($this->action->handle($request->validated()))
            ->response()
            ->setStatusCode(201);
    }
}
