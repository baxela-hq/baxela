<?php

namespace ErdFixtures\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * ERD test fixture model — erdfx_notes.post_id has NO database FK, so the
 * HasMany relation from ErdPost surfaces as a dashed app-level edge.
 */
class ErdNote extends Model
{
    use TenantScopedTrait;

    protected $table = 'erdfx_notes';
}
