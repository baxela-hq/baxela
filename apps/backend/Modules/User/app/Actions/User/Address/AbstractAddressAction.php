<?php

namespace Modules\User\Actions\User\Address;

use Modules\User\Models\User\Address;

abstract class AbstractAddressAction
{
    public function __construct(protected Address $model) {}
}
