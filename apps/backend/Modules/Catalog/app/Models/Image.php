<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\ImageFactory;
use Modules\Catalog\Schemas\Image\ImageCollectionEnum;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $media_id
 * @property ImageCollectionEnum|null $collection
 * @property string $url
 * @property int|null $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Image extends Model
{
    /** @use HasFactory<ImageFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = ImageSchema::TABLE;

    protected $fillable = [
        ImageSchema::PRODUCT_ID,
        ImageSchema::VARIANT_ID,
        ImageSchema::MEDIA_ID,
        ImageSchema::URL,
        ImageSchema::COLLECTION,
        ImageSchema::POSITION,
    ];

    public function casts(): array
    {
        return [
            ImageSchema::COLLECTION => ImageCollectionEnum::class,
        ];
    }

    protected static function newFactory(): ImageFactory
    {
        return ImageFactory::new();
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
