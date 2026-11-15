<?php

namespace Modules\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\CountryFactory;
use Modules\Core\Schemas\Country\CountrySchema;

/**
 * @property int $id
 * @property string $code
 * @property string $code3
 * @property string $name
 * @property string|null $native_name
 * @property string|null $emoji
 * @property string|null $phone_code
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Country extends Model
{
    use HasFactory;

    protected $table = CountrySchema::TABLE;

    protected $fillable = [
        CountrySchema::CODE,
        CountrySchema::CODE3,
        CountrySchema::NAME,
        CountrySchema::NATIVE_NAME,
        CountrySchema::EMOJI,
        CountrySchema::PHONE_CODE,
    ];

    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }
}
