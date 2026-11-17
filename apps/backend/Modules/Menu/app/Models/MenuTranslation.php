<?php

namespace Modules\Menu\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Menu\Database\Factories\MenuTranslationFactory;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema;

/**
 * @property int $id
 * @property int $menu_id
 * @property int $language_id
 * @property string $title
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Menu $menu
 */
class MenuTranslation extends Model
{
    /** @use HasFactory<MenuTranslationFactory> */
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

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
