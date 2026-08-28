<?php

namespace Modules\Core\Contracts\Events\Catalog;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class ProductUpdatedEvent extends AbstractBaseEvent
{
    public int $id;

    public string $title;

    public string $slug;

    public string $content;

    public ?string $description;

    public float $base_price;

    public string $type;

    public string $status;

    public string $created_at;

    public string $updated_at;
}
