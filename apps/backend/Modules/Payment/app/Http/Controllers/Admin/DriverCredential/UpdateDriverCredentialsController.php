<?php

namespace Modules\Payment\Http\Controllers\Admin\DriverCredential;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Actions\Admin\DriverCredential\UpdateDriverCredentialsAction;
use Modules\Payment\Http\Requests\Admin\DriverCredential\UpdateDriverCredentialsRequest;
use Modules\Payment\Transformers\Admin\DriverCredential\DriverCredentialResource;

class UpdateDriverCredentialsController extends Controller
{
    public function __construct(protected UpdateDriverCredentialsAction $action) {}

    public function __invoke(UpdateDriverCredentialsRequest $request, string $driver): JsonResource
    {
        return DriverCredentialResource::make(
            $this->action->handle($driver, $request->credentials()),
        );
    }
}
