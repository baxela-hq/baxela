<?php

namespace Modules\Core\Contracts\Events\Catalog;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class ProductCommentApprovedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $product_id;

    public int $user_id;

    public ?int $parent_id;

    public string $body;

    public ?int $rating;

    public string $status;

    public string $created_at;

    public string $updated_at;
}
