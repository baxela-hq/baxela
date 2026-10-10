<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Catalog\Database\Factories\BrandFactory;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Core\Models\Traits\SlugTrait;

class Brand extends Model
{
    use HasFactory;
    use SlugTrait;

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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }

    public function featuredItem(): MorphOne
    {
        return $this->morphOne(FeaturedItem::class, 'featuredable');
    }
}
