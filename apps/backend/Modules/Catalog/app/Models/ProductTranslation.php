<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\ProductTranslationFactory;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

class ProductTranslation extends Model
{
    use HasFactory;
    use SlugTrait;

    protected $table = ProductTranslationSchema::TABLE;

    protected $fillable = [
        ProductTranslationSchema::PRODUCT_ID,
        ProductTranslationSchema::LANGUAGE_ID,
        ProductTranslationSchema::TITLE,
        ProductTranslationSchema::SLUG,
        ProductTranslationSchema::CONTENT,
        ProductTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): ProductTranslationFactory
    {
        return ProductTranslationFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
