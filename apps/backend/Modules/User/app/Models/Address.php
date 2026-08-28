<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\User\Database\Factories\AddressFactory;
use Modules\User\Schemas\Address\AddressSchema;
use Modules\User\Schemas\Address\AddressTypeEnum;

class Address extends Model
{
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
