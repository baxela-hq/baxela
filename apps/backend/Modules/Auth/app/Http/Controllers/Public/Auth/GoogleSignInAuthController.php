<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\Public\Auth\GoogleSignInAction;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\Public\Auth\GoogleSignInRequest;
use Modules\Auth\Transformers\Public\Auth\SignInResource;

class GoogleSignInAuthController extends Controller
{
    /**
     * Same response envelope as the password sign-in: a successful Google
     * authentication is a normal sign-in (SignInResource). The third-party
     * OTP path returns its own envelope — no token exists until the
     * account is activated through the existing verification flow.
     *
     * @throws AuthException on every rejection path
     */
    public function __invoke(GoogleSignInRequest $request, GoogleSignInAction $action): JsonResponse
    {
        $result = $action->handle($request);

        if (isset($result->requires_activation)) {
            return response()->json([
                'data' => $result,
            ]);
        }

        return SignInResource::make($result)
            ->response();
    }
}
