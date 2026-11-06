<?php

namespace ErdFixtures\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ERD test fixture model — discovered by ModelRelationsScanner via
 * require_once (not autoloaded). Table exists only in the feature test's
 * sqlite schema.
 */
class ErdPost extends Model
{
    protected $table = 'erdfx_posts';

    /** @return HasMany<ErdDetail, $this> */
    public function details(): HasMany
    {
        return $this->hasMany(ErdDetail::class, 'post_id');
    }

    /** @return HasMany<ErdNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ErdNote::class, 'post_id');
    }

    /** @return HasOne<ErdSummary, $this> */
    public function summary(): HasOne
    {
        return $this->hasOne(ErdSummary::class, 'post_id');
    }

    /** @return BelongsToMany<ErdTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ErdTag::class, 'erdfx_post_tag', 'post_id', 'tag_id');
    }
}
