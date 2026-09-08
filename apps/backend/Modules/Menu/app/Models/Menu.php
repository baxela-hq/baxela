<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Menu\Database\Factories\MenuFactory;
use Modules\Menu\Schemas\Menu\MenuLocationEnum;
use Modules\Menu\Schemas\Menu\MenuSchema;

class Menu extends Model
{
    use HasFactory;

    protected $table = MenuSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        MenuSchema::LOCATION,
        MenuSchema::IS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            MenuSchema::LOCATION => MenuLocationEnum::class,
            MenuSchema::IS_ACTIVE => 'boolean',
        ];
    }

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }

    public function translations(): HasMany
    {
        return $this->hasMany(MenuTranslation::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(MenuLink::class);
    }
}
