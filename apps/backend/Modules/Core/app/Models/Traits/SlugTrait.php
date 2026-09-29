<?php

namespace Modules\Core\Models\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Core\Support\Slug;

trait SlugTrait
{
    protected function slug(): Attribute
    {
        // Unicode-aware on purpose: Str::slug() would transliterate
        // Persian titles into ASCII soup.
        return Attribute::make(
            set: fn (string $value) => Slug::slugify($value),
        );
    }
}
