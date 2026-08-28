<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PageTranslationFactory;
use Modules\Content\Schemas\Page\PageTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

class PageTranslation extends Model
{
    use HasFactory;
    use SlugTrait;

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

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
