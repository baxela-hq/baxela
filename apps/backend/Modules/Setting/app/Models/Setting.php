<?php

namespace Modules\Setting\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Setting\Database\Factories\SettingFactory;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;

/**
 * @property int $id
 * @property string|null $group
 * @property string|null $type
 * @property SettingNameEnum $name
 * @property string|null $value
 * @property bool $is_translatable
 * @property string|null $comment
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, Translation> $translations
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * The masked representation returned for the FX API key by the
     * admin resource — submitted back verbatim, it is rejected by
     * validation (review F8: the placeholder is never a key).
     */
    public const string MASKED_VALUE = '********';

    protected $table = SettingSchema::TABLE;

    protected $fillable = [
        SettingSchema::GROUP,
        SettingSchema::TYPE,
        SettingSchema::NAME,
        SettingSchema::VALUE,
        SettingSchema::IS_TRANSLATABLE,
        SettingSchema::COMMENT,
    ];

    protected function casts(): array
    {
        return [
            SettingSchema::GROUP => SettingGroupEnum::class,
            SettingSchema::TYPE => SettingTypeEnum::class,
            SettingSchema::NAME => SettingNameEnum::class,
            SettingSchema::IS_TRANSLATABLE => 'boolean',
        ];
    }

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
