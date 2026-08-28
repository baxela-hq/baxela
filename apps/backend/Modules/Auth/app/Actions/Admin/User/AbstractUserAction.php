<?php

namespace Modules\Auth\Actions\Admin\User;

use Modules\Auth\Models\User;

abstract class AbstractUserAction
{
    public function __construct(protected User $model) {}
}
