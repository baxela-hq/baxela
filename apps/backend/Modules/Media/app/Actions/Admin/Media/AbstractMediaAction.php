<?php

namespace Modules\Media\Actions\Admin\Media;

use Modules\Media\Models\Media;

class AbstractMediaAction
{
    public function __construct(protected Media $model) {}
}
