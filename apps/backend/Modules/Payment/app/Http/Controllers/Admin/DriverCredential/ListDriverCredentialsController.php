<?php

namespace Modules\Payment\Http\Controllers\Admin\DriverCredential;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Payment\Actions\Admin\DriverCredential\ListDriverCredentialsAction;
use Modules\Payment\Transformers\Admin\DriverCredential\DriverCredentialResource;

class ListDriverCredentialsController extends Controller
{
    public function __construct(protected ListDriverCredentialsAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return DriverCredentialResource::collection($this->action->handle());
    }
}
