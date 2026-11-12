<?php

namespace Modules\Auth\Http\Controllers\User\TwoFactor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\User\TwoFactor\ManageTwoFactorAction;
use Modules\Core\Utils\Auth;

class TwoFactorRecoveryCodesController extends Controller
{
    public function __construct(protected ManageTwoFactorAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'min:6', 'max:16']]);

        $result = $this->action->regenerateRecoveryCodes(Auth::user(), (string) $request->input('code'));

        return response()->json([
            'data' => $result,
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
