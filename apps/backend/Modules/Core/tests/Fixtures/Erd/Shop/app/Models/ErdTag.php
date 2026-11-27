<?php

namespace ErdFixtures\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * ERD test fixture model — target of the erdfx_post_tag pivot; the pivot
 * itself has no model, exercising the DB_PREFIX fallback resolution.
 */
class ErdTag extends Model
{
    use TenantScopedTrait;

    protected $table = 'erdfx_tags';
}
