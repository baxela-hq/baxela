<?php

namespace Modules\Payment\Http\Controllers\Admin\DriverCredential;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Payment\Actions\Admin\DriverCredential\UpdateDriverCredentialsAction;
use Modules\Payment\Transformers\Admin\DriverCredential\DriverCredentialResource;

class DeleteDriverCredentialController extends Controller
{
    public function __construct(protected UpdateDriverCredentialsAction $action) {}

    public function __invoke(string $driver, string $key): JsonResource
    {
        return DriverCredentialResource::make(
            $this->action->delete($driver, $key),
        );
    }
}
