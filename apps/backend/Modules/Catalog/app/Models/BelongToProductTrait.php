<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongToProductTrait
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
