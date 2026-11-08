<?php

namespace Modules\Core\Http\Controllers\Public\Region;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Actions\Public\Region\ListRegionsAction;

class ListRegionController extends Controller
{
    public function __construct(protected ListRegionsAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        return response()->json([
            'data' => $this->action->handle(strtoupper($validated['country_code'])),
        ]);
    }
}
