<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\LanguageFactory;
use Modules\Core\Schemas\Language\LanguageSchema;

class Language extends Model
{
    use HasFactory;

    protected $table = LanguageSchema::TABLE;

    protected $fillable = [
        LanguageSchema::LOCALE,
        LanguageSchema::NAME,
        LanguageSchema::NATIVE_NAME,
        LanguageSchema::CODE,
        LanguageSchema::CODE3,
        LanguageSchema::IS_RTL,
        LanguageSchema::IS_ACTIVE,
        LanguageSchema::IS_DEFAULT,
        LanguageSchema::POSITION,
        LanguageSchema::DATE_FORMAT,
        LanguageSchema::TIME_FORMAT,
    ];

    protected $casts = [
        LanguageSchema::IS_RTL => 'boolean',
        LanguageSchema::IS_ACTIVE => 'boolean',
        LanguageSchema::IS_DEFAULT => 'boolean',
    ];

    protected static function newFactory(): LanguageFactory
    {
        return LanguageFactory::new();
    }
}
