<?php

namespace Modules\Auth\Http\Controllers\User\TwoFactor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\User\TwoFactor\ManageTwoFactorAction;
use Modules\Core\Utils\Auth;

class TwoFactorStatusController extends Controller
{
    public function __construct(protected ManageTwoFactorAction $action) {}

    public function __invoke(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'data' => $this->action->status($user),
        ]);
    }
}
