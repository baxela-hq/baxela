<?php

namespace ErdFixtures\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERD test fixture model — its post() relation duplicates ErdPost::details()
 * on purpose, exercising app-edge deduplication.
 */
class ErdDetail extends Model
{
    protected $table = 'erdfx_details';

    /** @return BelongsTo<ErdPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ErdPost::class, 'post_id');
    }
}
