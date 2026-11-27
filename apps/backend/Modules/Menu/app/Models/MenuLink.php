<?php

namespace Modules\Menu\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Menu\Database\Factories\MenuLinkFactory;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;

/**
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int|null $position
 * @property string $url
 * @property MenuLinkTargetEnum $target
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Menu $menu
 * @property-read MenuLink|null $parent
 * @property-read Collection<int, MenuLink> $children
 * @property-read Collection<int, MenuLinkTranslation> $translations
 */
class MenuLink extends Model
{
    /** @use HasFactory<MenuLinkFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = MenuLinkSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        MenuLinkSchema::MENU_ID,
        MenuLinkSchema::PARENT_ID,
        MenuLinkSchema::POSITION,
        MenuLinkSchema::URL,
        MenuLinkSchema::TARGET,
    ];

    protected function casts(): array
    {
        return [
            MenuLinkSchema::TARGET => MenuLinkTargetEnum::class,
        ];
    }

    protected static function newFactory(): MenuLinkFactory
    {
        return MenuLinkFactory::new();
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return BelongsTo<MenuLink, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuLink::class, MenuLinkSchema::PARENT_ID);
    }

    /** @return HasMany<MenuLink, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MenuLink::class, MenuLinkSchema::PARENT_ID);
    }

    /** @return HasMany<MenuLinkTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuLinkTranslation::class);
    }
}
