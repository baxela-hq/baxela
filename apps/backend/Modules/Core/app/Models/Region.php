<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Schemas\Region\RegionSchema;

/**
 * An ISO 3166-2 subdivision (gap-C1a D6): seed-maintained (no admin CRUD
 * in v1), consumed by RegionCodeRule and the public regions endpoint.
 *
 * @mixin Builder
 */
class Region extends Model
{
    protected $table = RegionSchema::TABLE;

    protected $fillable = [
        RegionSchema::COUNTRY_CODE,
        RegionSchema::CODE,
        RegionSchema::NAME,
    ];
}
