<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Menu\Database\Factories\MenuTranslationFactory;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema;

class MenuTranslation extends Model
{
    use HasFactory;

    protected $table = MenuTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        MenuTranslationSchema::MENU_ID,
        MenuTranslationSchema::LANGUAGE_ID,
        MenuTranslationSchema::TITLE,
        MenuTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): MenuTranslationFactory
    {
        return MenuTranslationFactory::new();
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
