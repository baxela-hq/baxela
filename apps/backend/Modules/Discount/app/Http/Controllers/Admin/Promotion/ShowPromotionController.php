<?php

namespace Modules\Discount\Http\Controllers\Admin\Promotion;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Discount\Actions\Admin\Promotion\ShowPromotionAction;
use Modules\Discount\Transformers\Admin\Promotion\PromotionResource;

class ShowPromotionController extends Controller
{
    public function __construct(protected ShowPromotionAction $action) {}

    public function __invoke(string $id): JsonResponse
    {
        return PromotionResource::make($this->action->handle($id))->response();
    }
}
