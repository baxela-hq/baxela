<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\BrandTranslationFactory;
use Modules\Catalog\Schemas\Brand\BrandTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $brand_id
 * @property int|null $language_id
 * @property string $title
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class BrandTranslation extends Model
{
    /** @use HasFactory<BrandTranslationFactory> */
    use HasFactory;

    use SlugTrait;
    use TenantScopedTrait;

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

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
