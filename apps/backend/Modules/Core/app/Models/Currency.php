<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\CurrencyFactory;
use Modules\Core\Schemas\Currency\CurrencySchema;

class Currency extends Model
{
    use HasFactory;

    protected $table = CurrencySchema::TABLE;

    protected $fillable = [
        CurrencySchema::CODE,
        CurrencySchema::NAME,
        CurrencySchema::NATIVE_NAME,
        CurrencySchema::DECIMAL_PLACES,
        CurrencySchema::SYMBOL,
        CurrencySchema::IS_SYMBOL_RIGHT,
        CurrencySchema::IS_DEFAULT,
    ];

    protected $casts = [
        CurrencySchema::IS_SYMBOL_RIGHT => 'boolean',
        CurrencySchema::IS_DEFAULT => 'boolean',
    ];

    protected static function newFactory(): CurrencyFactory
    {
        return CurrencyFactory::new();
    }
}
