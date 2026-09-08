<?php

namespace Modules\Auth\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Actions\Admin\Account\ShowAccountAction;
use Modules\Auth\Transformers\Admin\Account\AccountResource;

class ShowAccountController extends Controller
{
    public function __construct(protected ShowAccountAction $action) {}

    public function __invoke(Request $request): AccountResource
    {
        return AccountResource::make($this->action->handle($request));
    }
}
