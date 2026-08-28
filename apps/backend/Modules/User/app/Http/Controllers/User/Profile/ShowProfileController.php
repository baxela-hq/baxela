<?php

namespace Modules\User\Http\Controllers\User\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\User\Actions\User\Profile\ShowProfileAction;
use Modules\User\Transformers\User\Profile\ProfileResource;

class ShowProfileController extends Controller
{
    public function __construct(protected ShowProfileAction $action) {}

    public function __invoke(Request $request): ProfileResource
    {
        return ProfileResource::make($this->action->handle());
    }
}
