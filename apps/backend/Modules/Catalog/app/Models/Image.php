<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\ImageFactory;
use Modules\Catalog\Schemas\Image\ImageCollectionEnum;
use Modules\Catalog\Schemas\Image\ImageSchema;

class Image extends Model
{
    use HasFactory;

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
