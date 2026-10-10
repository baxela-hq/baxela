<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\BrandTranslationFactory;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

class BrandTranslation extends Model
{
    use HasFactory;
    use SlugTrait;

    protected $table = BrandTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        BrandTranslationSchema::LANGUAGE_ID,
        BrandTranslationSchema::TITLE,
        BrandTranslationSchema::SLUG,
        BrandTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): BrandTranslationFactory
    {
        return BrandTranslationFactory::new();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
