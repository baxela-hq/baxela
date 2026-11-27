<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PageTranslationFactory;
use Modules\Content\Schemas\Page\PageTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $page_id
 * @property int|null $language_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Page $page
 */
class PageTranslation extends Model
{
    /** @use HasFactory<PageTranslationFactory> */
    use HasFactory;

    use SlugTrait;
    use TenantScopedTrait;

    protected $table = PageTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PageTranslationSchema::LANGUAGE_ID,
        PageTranslationSchema::TITLE,
        PageTranslationSchema::SLUG,
        PageTranslationSchema::CONTENT,
        PageTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): PageTranslationFactory
    {
        return PageTranslationFactory::new();
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
