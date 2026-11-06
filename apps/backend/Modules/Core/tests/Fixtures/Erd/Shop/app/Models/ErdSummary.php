<?php

namespace ErdFixtures\Shop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ERD test fixture model — erdfx_summaries.post_id is unique with NO
 * database FK, so the HasOne relation renders as a dashed one-to-one.
 */
class ErdSummary extends Model
{
    protected $table = 'erdfx_summaries';
}
