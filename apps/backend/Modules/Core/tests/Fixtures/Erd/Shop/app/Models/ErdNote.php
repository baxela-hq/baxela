<?php

namespace ErdFixtures\Shop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ERD test fixture model — erdfx_notes.post_id has NO database FK, so the
 * HasMany relation from ErdPost surfaces as a dashed app-level edge.
 */
class ErdNote extends Model
{
    protected $table = 'erdfx_notes';
}
