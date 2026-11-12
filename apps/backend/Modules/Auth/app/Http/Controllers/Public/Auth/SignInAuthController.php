<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\Public\Auth\SignInAction;
use Modules\Auth\Exceptions\AccountAlreadyActivatedException;
use Modules\Auth\Exceptions\AccountNotActivatedException;
use Modules\Auth\Exceptions\InvalidCredentialsException;
use Modules\Auth\Http\Requests\Public\Auth\SignInRequest;
use Modules\Auth\Transformers\Public\Auth\SignInResource;
use Random\RandomException;

class SignInAuthController extends Controller
{
    /**
     * @throws AccountAlreadyActivatedException
     * @throws AccountNotActivatedException
     * @throws InvalidCredentialsException
     * @throws RandomException
     */
    public function __invoke(SignInRequest $request, SignInAction $action): JsonResponse
    {
        $result = $action->handle($request);

        // S.1 D3/D9: 2FA challenge responses return directly — no
        // SignInResource (there is no token to shape).
        if (isset($result->two_factor_required) || isset($result->two_factor_enrollment_required)) {
            return response()->json([
                'data' => $result,
            ]);
        }

        return SignInResource::make($result)
            ->response();
    }
}
