<?php

namespace Modules\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\LanguageFactory;
use Modules\Core\Schemas\Language\LanguageSchema;

/**
 * @property int $id
 * @property string $locale
 * @property string $name
 * @property string|null $native_name
 * @property string $code
 * @property string $code3
 * @property bool $is_rtl
 * @property bool $is_active
 * @property bool $is_default
 * @property int|null $position
 * @property string|null $date_format
 * @property string|null $time_format
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
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
