<?php

namespace Modules\Auth\Http\Controllers\Public\Auth;

use App\Http\Controllers\Controller;
use Modules\Auth\Actions\Public\Auth\SignInAuthAction;
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
    public function __invoke(SignInRequest $request, SignInAuthAction $action): SignInResource
    {
        return SignInResource::make($action->handle($request));
    }
}
