<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Database\Factories\ProductSeoTranslationFactory;
use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $language_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $open_graph_title
 * @property string|null $open_graph_description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ProductSeoTranslation extends Model
{
    use BelongToProductTrait;

    /** @use HasFactory<ProductSeoTranslationFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = ProductSeoTranslationSchema::TABLE;

    protected $fillable = [
        ProductSeoTranslationSchema::PRODUCT_ID,
        ProductSeoTranslationSchema::LANGUAGE_ID,
        ProductSeoTranslationSchema::META_TITLE,
        ProductSeoTranslationSchema::META_DESCRIPTION,
        ProductSeoTranslationSchema::OPEN_GRAPH_TITLE,
        ProductSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION,
    ];

    protected static function newFactory(): ProductSeoTranslationFactory
    {
        return ProductSeoTranslationFactory::new();
    }
}
