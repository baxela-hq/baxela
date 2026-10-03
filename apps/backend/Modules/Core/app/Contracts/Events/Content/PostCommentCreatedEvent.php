<?php

namespace Modules\Core\Contracts\Events\Content;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class PostCommentCreatedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $post_id;

    public int $user_id;

    public ?int $parent_id;

    public string $body;

    public string $status;

    public string $created_at;

    public string $updated_at;
}
