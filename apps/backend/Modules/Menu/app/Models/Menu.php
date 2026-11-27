<?php

namespace Modules\Menu\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Menu\Database\Factories\MenuFactory;
use Modules\Menu\Schemas\Menu\MenuLocationEnum;
use Modules\Menu\Schemas\Menu\MenuSchema;

/**
 * @property int $id
 * @property MenuLocationEnum $location
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, MenuLink> $links
 * @property-read Collection<int, MenuTranslation> $translations
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    use TenantScopedTrait;

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

    /** @return HasMany<MenuTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuTranslation::class);
    }

    /** @return HasMany<MenuLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(MenuLink::class);
    }
}
