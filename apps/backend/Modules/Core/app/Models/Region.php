<?php

namespace Modules\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Schemas\Region\RegionSchema;

/**
 * An ISO 3166-2 subdivision (gap-C1a D6): seed-maintained (no admin CRUD
 * in v1), consumed by RegionCodeRule and the public regions endpoint.
 *
 * @mixin Builder
 */
/**
 * @property int $id
 * @property string $country_code
 * @property string $code
 * @property string $name
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @mixin Builder<Region>
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
