<?php

namespace Modules\Setting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Setting\Database\Factories\SettingFactory;
use Modules\Setting\Schemas\Setting\SettingGroupEnum;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Modules\Setting\Schemas\Setting\SettingTypeEnum;

class Setting extends Model
{
    use HasFactory;

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
