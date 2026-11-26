<?php

namespace Modules\Auth\Http\Controllers\Admin\Token;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Auth\Http\Requests\Admin\Token\MachineTokenRequest;
use Modules\Auth\Models\User;
use Modules\Core\Exceptions\TenantContextMissingException;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
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

        // A machine token is issued for exactly one store (multi-tenancy
        // §9.7): the store the issuing admin is operating in. Machine
        // routes resolve their tenant from this column — never from a
        // header — and it never changes after issuance.
        $tenantId = app(TenantContext::class)->tenantId();

        if ($tenantId === null) {
            throw new TenantContextMissingException;
        }

        $record = DB::transaction(function () use ($user, $validated, &$token, $tenantId) {
            $token = $user->createToken(
                $validated['name'],
                $validated['abilities'],
                now()->addDays($validated['expires_days']),
            );

            $token->accessToken->forceFill([
                TenantColumn::TENANT_ID => $tenantId,
            ])->save();

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
                // The store this token is bound to (multi-tenancy §9.7).
                TenantColumn::TENANT_ID => $record->getAttribute(TenantColumn::TENANT_ID),
                'expires_at' => optional($record->expires_at)->toIso8601String(),
                // Plaintext shown exactly once (gap-C2 D14).
                'token' => $token->plainTextToken,
            ],
        ], Response::HTTP_CREATED);
    }
}
