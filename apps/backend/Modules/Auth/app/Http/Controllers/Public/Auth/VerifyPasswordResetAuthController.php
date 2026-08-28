<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Actions\Public\Auth\VerifyPasswordResetAuthAction;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\Public\Auth\VerifyPasswordResetOtpRequest;
use Modules\Auth\Transformers\Public\DefaultResource;
use Random\RandomException;

class VerifyPasswordResetAuthController extends Controller
{
    /**
     * @throws AuthException
     * @throws RandomException|ValidationException
     */
    public function __invoke(VerifyPasswordResetOtpRequest $request, VerifyPasswordResetAuthAction $action): DefaultResource
    {
        $action->handle($request);

        return DefaultResource::make($request);
    }
}
