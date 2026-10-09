<?php

namespace Modules\Discount\Http\Controllers\Admin\Promotion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Discount\Actions\Admin\Promotion\ListPromotionAction;
use Modules\Discount\Transformers\Admin\Promotion\PromotionResource;

class ListPromotionController extends Controller
{
    public function __construct(protected ListPromotionAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return PromotionResource::collection($this->action->handle());
    }
}
