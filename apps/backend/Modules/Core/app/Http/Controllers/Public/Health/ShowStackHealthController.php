<?php

namespace Modules\Core\Http\Controllers\Public\Health;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Actions\Public\Health\ShowStackHealthAction;
use Modules\Core\Schemas\Health\StackHealthSchema;

class ShowStackHealthController extends Controller
{
    public function __construct(protected ShowStackHealthAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $health = $this->action->handle($request->boolean('strict'));

        $down = $health[StackHealthSchema::STATUS] === StackHealthSchema::STATUS_DOWN;

        return response()->json(
            ['data' => $health],
            $down ? 503 : 200,
        );
    }
}
