<?php

namespace Modules\Content\Actions\Public\Post;

use Modules\Content\Models\Post;

abstract class AbstractPostAction
{
    public function __construct(protected Post $model) {}
}
