<?php

namespace Modules\Auth\Http\Controllers\Admin\Token;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Metadata only — the plaintext is never retrievable.
 */
class ListMachineTokensController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return AnonymousResourceCollection::make(
            PersonalAccessToken::query()
                ->whereIn('abilities', [['orders.read'], ['products.read']])
                ->orWhereJsonContains('abilities', 'orders.read')
                ->orWhereJsonContains('abilities', 'products.read')
                ->orderByDesc('created_at')
                ->get(['id', 'name', 'abilities', 'created_at', 'expires_at', 'last_used_at'])
                ->map(fn ($token): array => [
                    'id' => $token->getKey(),
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                    'last_used_at' => $token->last_used_at,
                ]),
        );
    }
}
