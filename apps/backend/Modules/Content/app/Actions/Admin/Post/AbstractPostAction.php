<?php

namespace Modules\Content\Actions\Admin\Post;

use Modules\Content\Models\Post;

abstract class AbstractPostAction
{
    public function __construct(protected Post $model) {}
}
