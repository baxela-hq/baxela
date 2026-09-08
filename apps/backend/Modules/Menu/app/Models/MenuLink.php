<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Menu\Database\Factories\MenuLinkFactory;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTargetEnum;

class MenuLink extends Model
{
    use HasFactory;

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

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuLink::class, MenuLinkSchema::PARENT_ID);
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuLink::class, MenuLinkSchema::PARENT_ID);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(MenuLinkTranslation::class);
    }
}
