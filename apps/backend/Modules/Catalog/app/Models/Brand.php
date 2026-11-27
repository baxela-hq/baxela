<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Catalog\Database\Factories\BrandFactory;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Core\Models\Traits\SlugTrait;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int|null $position
 * @property int|null $image_media_id
 * @property string|null $image_url
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    use SlugTrait;
    use TenantScopedTrait;

    protected $table = BrandSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        BrandSchema::POSITION,
        BrandSchema::IMAGE_MEDIA_ID,
        BrandSchema::IMAGE_URL,
    ];

    protected static function newFactory(): BrandFactory
    {
        return BrandFactory::new();
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<BrandTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }

    /** @return MorphOne<FeaturedItem, $this> */
    public function featuredItem(): MorphOne
    {
        return $this->morphOne(FeaturedItem::class, 'featuredable');
    }
}
