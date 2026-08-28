<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Modules\Auth\Actions\Public\Auth\RequestPasswordResetAuthAction;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\Public\Auth\RequestPasswordResetOtpRequest;
use Modules\Auth\Transformers\Public\DefaultResource;
use Random\RandomException;

class RequestPasswordResetAuthController extends Controller
{
    /**
     * @throws AuthException
     * @throws RandomException
     */
    public function __invoke(RequestPasswordResetOtpRequest $request, RequestPasswordResetAuthAction $action): DefaultResource
    {
        $action->handle($request);

        return DefaultResource::make($request);
    }
}
