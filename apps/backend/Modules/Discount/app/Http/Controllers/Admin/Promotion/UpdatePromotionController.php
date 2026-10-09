<?php

namespace Modules\Discount\Http\Controllers\Admin\Promotion;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Discount\Actions\Admin\Promotion\UpdatePromotionAction;
use Modules\Discount\Http\Requests\Admin\Promotion\PromotionRequest;
use Modules\Discount\Transformers\Admin\Promotion\PromotionResource;

class UpdatePromotionController extends Controller
{
    public function __construct(protected UpdatePromotionAction $action) {}

    public function __invoke(PromotionRequest $request, string $id): JsonResponse
    {
        return PromotionResource::make($this->action->handle($id, $request->validated()))->response();
    }
}
