<?php

namespace Modules\User\Http\Controllers\User\Profile;

use App\Http\Controllers\Controller;
use Modules\User\Actions\User\Profile\UpdateProfileAction;
use Modules\User\Http\Requests\User\Profile\ProfileRequest;
use Modules\User\Transformers\User\Profile\ProfileResource;

class UpdateProfileController extends Controller
{
    public function __construct(protected UpdateProfileAction $action) {}

    public function __invoke(ProfileRequest $request): ProfileResource
    {
        return ProfileResource::make($this->action->handle($request));
    }
}
