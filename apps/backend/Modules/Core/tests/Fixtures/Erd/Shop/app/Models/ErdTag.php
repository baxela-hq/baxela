<?php

namespace ErdFixtures\Shop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ERD test fixture model — target of the erdfx_post_tag pivot; the pivot
 * itself has no model, exercising the DB_PREFIX fallback resolution.
 */
class ErdTag extends Model
{
    protected $table = 'erdfx_tags';
}
