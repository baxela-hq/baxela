<?php

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Models\Country;
use Modules\Core\Schemas\Country\CountrySchema;
use Modules\Shipping\Database\Factories\ZoneFactory;
use Modules\Shipping\Schemas\Zone\ZoneCountrySchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

class Zone extends Model
{
    use HasFactory;

    protected $table = ZoneSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        ZoneSchema::NAME,
        ZoneSchema::IS_ACTIVE,
        ZoneSchema::POSITION,
    ];

    protected function casts(): array
    {
        return [
            ZoneSchema::IS_ACTIVE => 'boolean',
        ];
    }

    protected static function newFactory(): ZoneFactory
    {
        return ZoneFactory::new();
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, ZoneCountrySchema::TABLE,
            ZoneCountrySchema::ZONE_ID, ZoneCountrySchema::COUNTRY_CODE,
            ZoneSchema::ID, CountrySchema::CODE);
    }
}
