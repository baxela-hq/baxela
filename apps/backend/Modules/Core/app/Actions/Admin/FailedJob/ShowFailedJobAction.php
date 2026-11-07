<?php

namespace Modules\Core\Actions\Admin\FailedJob;

use Modules\Core\Models\FailedJob;

class ShowFailedJobAction extends AbstractFailedJobAction
{
    public function handle(string $uuid): FailedJob
    {
        return $this->findFailedJob($uuid);
    }
}
