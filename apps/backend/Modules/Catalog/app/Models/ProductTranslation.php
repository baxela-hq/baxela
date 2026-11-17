<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\ProductTranslationFactory;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $language_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ProductTranslation extends Model
{
    /** @use HasFactory<ProductTranslationFactory> */
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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
