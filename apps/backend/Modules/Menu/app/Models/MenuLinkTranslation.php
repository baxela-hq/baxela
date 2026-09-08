<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Menu\Database\Factories\MenuLinkTranslationFactory;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema;

class MenuLinkTranslation extends Model
{
    use HasFactory;

    protected $table = MenuLinkTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        MenuLinkTranslationSchema::MENU_LINK_ID,
        MenuLinkTranslationSchema::LANGUAGE_ID,
        MenuLinkTranslationSchema::TITLE,
        MenuLinkTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): MenuLinkTranslationFactory
    {
        return MenuLinkTranslationFactory::new();
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(MenuLink::class);
    }
}
