<?php

namespace Modules\Core\Http\Controllers\Admin\Currency;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Core\Actions\Admin\Currency\SyncExchangeRatesAction;

/**
 * The admin "Sync now" path (Part E) — the same engine as the
 * scheduled command, with force bypassing the enabled/due/cooldown
 * gates. A lock collision is HTTP 409 (review: transient outcome),
 * never a mutation of the running sync's status.
 */
class SyncCurrencyRatesController extends Controller
{
    public function __construct(protected SyncExchangeRatesAction $action) {}

    public function __invoke(): JsonResponse
    {
        $result = $this->action->sync(force: true);

        if ($result['outcome'] === SyncExchangeRatesAction::OUTCOME_SYNC_IN_PROGRESS) {
            return response()->json([
                'code' => 'core.fx_sync_in_progress',
                'message' => __('core::errors.fx.sync_in_progress'),
            ], 409);
        }

        return response()->json([
            'data' => [
                'outcome' => $result['outcome'],
                'status' => $result['status'],
            ],
        ]);
    }
}
