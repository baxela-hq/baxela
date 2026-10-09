<?php

namespace Modules\Discount\Http\Controllers\Admin\Promotion;

use App\Http\Controllers\Controller;
use Modules\Discount\Actions\Admin\Promotion\DeletePromotionAction;
use Symfony\Component\HttpFoundation\Response;

class DeletePromotionController extends Controller
{
    public function __construct(protected DeletePromotionAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
