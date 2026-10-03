<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PostTranslationFactory;
use Modules\Content\Schemas\Post\PostTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

class PostTranslation extends Model
{
    use HasFactory;
    use SlugTrait;

    protected $table = PostTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PostTranslationSchema::LANGUAGE_ID,
        PostTranslationSchema::TITLE,
        PostTranslationSchema::SLUG,
        PostTranslationSchema::CONTENT,
        PostTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): PostTranslationFactory
    {
        return PostTranslationFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
