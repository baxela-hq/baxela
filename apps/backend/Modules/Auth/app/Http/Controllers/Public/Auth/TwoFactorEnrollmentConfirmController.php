<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Actions\Public\Auth\CompleteTwoFactorEnrollmentAction;
use Modules\Auth\Transformers\Public\User\UserResource;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;

class TwoFactorEnrollmentConfirmController extends Controller
{
    public function __construct(protected CompleteTwoFactorEnrollmentAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'code' => ['required', 'string', 'min:6', 'max:16'],
        ]);

        $result = $this->action->confirm(
            (string) $request->input('challenge_token'),
            (string) $request->input('code'),
        );

        $coreGateway = app(CoreGatewayInterface::class);

        // Recovery codes are returned exactly once — never cacheable.
        return response()->json([
            'data' => [
                'token' => $result->token,
                'user' => UserResource::make($result->user),
                'settings' => [
                    'currency' => $coreGateway->getDefaultCurrency(),
                    'language' => $coreGateway->getDefaultLanguage(),
                ],
                'recovery_codes' => $result->recovery_codes,
            ],
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
