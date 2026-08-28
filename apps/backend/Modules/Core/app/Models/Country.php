<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\CountryFactory;
use Modules\Core\Schemas\Country\CountrySchema;

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
