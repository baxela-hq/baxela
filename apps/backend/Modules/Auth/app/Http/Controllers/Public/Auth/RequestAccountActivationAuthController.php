<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Modules\Auth\Actions\Public\Auth\RequestAccountActivationAuthAction;
use Modules\Auth\Exceptions\AuthException;
use Modules\Auth\Http\Requests\Public\Auth\RequestAccountActivationOtpRequest;
use Modules\Auth\Transformers\Public\DefaultResource;
use Random\RandomException;

class RequestAccountActivationAuthController extends Controller
{
    /**
     * @throws AuthException
     * @throws RandomException
     */
    public function __invoke(RequestAccountActivationOtpRequest $request, RequestAccountActivationAuthAction $action): DefaultResource
    {
        $action->handle($request);

        return DefaultResource::make($request);
    }
}
