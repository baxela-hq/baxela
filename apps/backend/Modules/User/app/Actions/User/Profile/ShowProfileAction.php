<?php

namespace Modules\User\Actions\User\Profile;

use Modules\Core\Utils\Auth;
use Modules\User\Models\Profile;

class ShowProfileAction extends AbstractProfileAction
{
    public function handle(): ?Profile
    {
        return $this->model->getByUserId(Auth::id());
    }
}
