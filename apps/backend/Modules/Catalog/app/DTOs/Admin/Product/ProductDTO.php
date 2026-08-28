<?php

namespace Modules\Catalog\DTOs\Admin\Product;

use Modules\Core\Contracts\DTOs\DTOInterface;
use Modules\Core\Contracts\DTOs\DTOTrait;

class ProductDTO implements DTOInterface
{
    use DTOTrait;

    public string $title;

    public string $slug;

    public string $content;

    public string $description;

    public string $language;

    public float $base_price;

    public string $type;

    public string $status;

    public bool $is_published;
}
