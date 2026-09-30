<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;

abstract class AbstractInventoryStockAction
{
    /**
     * Eager-load path backing the admin stock rows: the variant plus its
     * product's translations (product title column).
     */
    protected const string WITH_VARIANT_PRODUCT = InventoryStockSchema::RES_VARIANT.'.'.
        VariantSchema::RES_PRODUCT.'.'.
        ProductSchema::RES_TRANSLATIONS;

    public function __construct(protected InventoryStock $model) {}
}
