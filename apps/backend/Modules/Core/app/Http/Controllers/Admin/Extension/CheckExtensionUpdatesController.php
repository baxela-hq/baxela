<?php

namespace Modules\Core\Http\Controllers\Admin\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Actions\Admin\Extension\CheckExtensionUpdatesAction;

class CheckExtensionUpdatesController extends Controller
{
    public function __construct(protected CheckExtensionUpdatesAction $action) {}

    public function __invoke(): JsonResponse
    {
        // The action's envelope carries a `data` key (the marketplace's
        // per-extension map), which makes Laravel's automatic resource
        // wrapping skip itself — so the envelope is built explicitly,
        // keeping the house `data.`-prefixed wire shape every other
        // admin endpoint uses. Degraded availability states are data,
        // not errors: HTTP 200 with the honest envelope (P5.3).
        return response()->json([
            'data' => $this->action->handle(),
        ]);
    }
}
