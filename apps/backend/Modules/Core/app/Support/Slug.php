<?php

namespace Modules\Core\Support;

/**
 * Unicode-safe slugifier. Str::slug() transliterates everything
 * non-ASCII away (Persian text ends up as 'tyshrt-klasyk-mrdanh' or an
 * empty string), which is wrong for a Persian-first store — so this
 * keeps Unicode letters: lowercase, punctuation stripped, whitespace
 * and separator runs collapsed to single dashes.
 */
final class Slug
{
    public static function slugify(string $value): string
    {
        $slug = mb_strtolower(trim($value));
        $slug = (string) preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $slug);
        $slug = (string) preg_replace('/[\s-]+/u', '-', $slug);

        return trim($slug, '-');
    }
}
