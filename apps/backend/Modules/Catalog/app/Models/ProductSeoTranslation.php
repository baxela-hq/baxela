<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Database\Factories\ProductSeoTranslationFactory;
use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema;

class ProductSeoTranslation extends Model
{
    use BelongToProductTrait;
    use HasFactory;

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
