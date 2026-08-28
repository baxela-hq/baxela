<?php

namespace Modules\User\Actions\User\Profile;

use Modules\User\Models\Profile;

abstract class AbstractProfileAction
{
    public function __construct(protected Profile $model) {}
}
