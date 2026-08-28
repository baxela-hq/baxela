<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Actions\Public\Auth\VerifyAccountActivationAuthAction;
use Modules\Auth\Http\Requests\Public\Auth\VerifyAccountActivationOtpRequest;
use Modules\Auth\Transformers\Public\DefaultResource;

class VerifyAccountActivationAuthController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function __invoke(VerifyAccountActivationOtpRequest $request, VerifyAccountActivationAuthAction $action): DefaultResource
    {
        $action->handle($request);

        return DefaultResource::make($request);
    }
}
