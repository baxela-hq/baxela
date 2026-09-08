<?php

namespace Modules\Contact\Actions\Admin\ContactMessage;

use Modules\Contact\Models\ContactMessage;

abstract class AbstractContactMessageAction
{
    public function __construct(protected ContactMessage $model) {}
}
