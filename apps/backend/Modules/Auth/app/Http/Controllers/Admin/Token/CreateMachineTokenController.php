<?php

namespace Modules\Auth\Http\Controllers\Admin\Token;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Auth\Http\Requests\Admin\Token\MachineTokenRequest;
use Modules\Auth\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mint a machine token (gap-C2 D14): audit-logged, plaintext shown ONCE.
 */
class CreateMachineTokenController extends Controller
{
    public function __invoke(MachineTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->findOrFail($validated['user_id']);

        $token = null;

        $record = DB::transaction(function () use ($user, $validated, &$token) {
            $token = $user->createToken(
                $validated['name'],
                $validated['abilities'],
                now()->addDays($validated['expires_days']),
            );

            // Audit trail (gap-C2 D14) — direct write, mirroring the
            // audit listener's shape; token plaintext never stored.
            AuditLog::query()->create([
                AuditLogSchema::MODULE => 'Auth',
                AuditLogSchema::ACTION => 'machine_token.created',
                AuditLogSchema::ENTITY => 'machine_token',
                AuditLogSchema::ENTITY_ID => (string) $token->accessToken->getKey(),
                AuditLogSchema::PROPERTIES => [
                    'user_id' => $user->getKey(),
                    'abilities' => $validated['abilities'],
                    'expires_at' => optional($token->accessToken->expires_at)->toIso8601String(),
                ],
            ]);

            return $token->accessToken;
        });

        return response()->json([
            'data' => [
                'id' => $record->getKey(),
                'name' => $record->name,
                'abilities' => $record->abilities,
                'expires_at' => optional($record->expires_at)->toIso8601String(),
                // Plaintext shown exactly once (gap-C2 D14).
                'token' => $token->plainTextToken,
            ],
        ], Response::HTTP_CREATED);
    }
}
