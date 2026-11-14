<?php

namespace Modules\User\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\User\Database\Factories\AddressFactory;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Address\AddressTypeEnum;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $full_name
 * @property string $phone
 * @property string $address_line
 * @property string $city
 * @property string|null $postal_code
 * @property string|null $country_code
 * @property string|null $region_code
 * @property string|null $vat_id
 * @property bool $is_default
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    protected $table = AddressSchema::TABLE;

    protected $fillable = [
        AddressSchema::USER_ID,
        AddressSchema::TYPE,
        AddressSchema::FULL_NAME,
        AddressSchema::PHONE,
        AddressSchema::ADDRESS_LINE,
        AddressSchema::CITY,
        AddressSchema::POSTAL_CODE,
        AddressSchema::COUNTRY_CODE,
        AddressSchema::REGION_CODE,
        AddressSchema::VAT_ID,
        AddressSchema::IS_DEFAULT,
    ];

    protected function casts(): array
    {
        return [
            AddressSchema::TYPE => AddressTypeEnum::class,
            AddressSchema::IS_DEFAULT => 'boolean',
        ];
    }

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }
}
