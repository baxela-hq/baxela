<?php

namespace Modules\Core\Models\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

trait SlugTrait
{
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Str::slug($value),
        );
    }
}
