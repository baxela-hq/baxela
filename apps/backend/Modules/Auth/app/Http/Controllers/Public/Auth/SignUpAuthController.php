<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Modules\Auth\Actions\Public\Auth\SignUpAuthAction;
use Modules\Auth\Exceptions\OtpTooManyRequestsException;
use Modules\Auth\Http\Requests\Public\Auth\SignUpRequest;
use Modules\Auth\Transformers\Public\DefaultResource;
use Random\RandomException;
use Symfony\Component\HttpFoundation\Response;

class SignUpAuthController extends Controller
{
    /**
     * @throws RandomException
     * @throws OtpTooManyRequestsException
     */
    public function __invoke(SignUpRequest $request, SignUpAuthAction $action): Response
    {
        $action->handle($request);

        // TODO: add try catch and error response
        return DefaultResource::make($request)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
