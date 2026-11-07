<?php

namespace Modules\Auth\Http\Controllers\Admin\Token;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;

/**
 * Revocation is immediate and audit-logged (gap-C2 D14).
 */
class RevokeMachineTokenController extends Controller
{
    public function __invoke(Request $request, string $id): Response
    {
        $token = PersonalAccessToken::query()->findOrFail($id);

        DB::transaction(function () use ($token): void {
            $token->delete();

            AuditLog::query()->create([
                AuditLogSchema::MODULE => 'Auth',
                AuditLogSchema::ACTION => 'machine_token.revoked',
                AuditLogSchema::ENTITY => 'machine_token',
                AuditLogSchema::ENTITY_ID => (string) $token->getKey(),
                AuditLogSchema::PROPERTIES => ['name' => $token->name],
            ]);
        });

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
