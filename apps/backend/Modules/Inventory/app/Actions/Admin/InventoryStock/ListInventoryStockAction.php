<?php

namespace Modules\Inventory\Actions\Admin\InventoryStock;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Inventory\Schemas\InventoryStock\InventoryStockSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListInventoryStockAction extends AbstractInventoryStockAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = InventoryStockSchema::TABLE.'.'.InventoryStockSchema::ID;

        return QueryBuilder::for($this->model->newQuery())
            ->allowedFilters(
                AllowedFilter::exact(InventoryStockSchema::VARIANT_ID),
                AllowedFilter::callback(
                    'product_id',
                    fn ($query, $value) => $query->whereHas(
                        InventoryStockSchema::RES_VARIANT,
                        fn ($variant) => $variant->where(VariantSchema::PRODUCT_ID, intval($value))
                    )
                ),
                AllowedFilter::callback(
                    'sku',
                    fn ($query, $value) => $query->whereHas(
                        InventoryStockSchema::RES_VARIANT,
                        fn ($variant) => $variant->where(VariantSchema::SKU, 'like', "%{$value}%")
                    )
                ),
                AllowedFilter::callback(
                    'low_stock',
                    fn ($query, $value) => $query->when(
                        ! in_array($value, ['', '0', 'false'], true),
                        fn ($q) => $q->where(
                            InventoryStockSchema::QUANTITY,
                            '<=',
                            intval(config('inventory.low_stock_threshold', 5))
                        )
                    )
                ),
            )
            ->allowedSorts(
                InventoryStockSchema::ID,
                InventoryStockSchema::QUANTITY,
            )
            ->select([
                $id,
                InventoryStockSchema::VARIANT_ID,
                InventoryStockSchema::QUANTITY,
                InventoryStockSchema::TABLE.'.'.InventoryStockSchema::CREATED_AT,
                InventoryStockSchema::TABLE.'.'.InventoryStockSchema::UPDATED_AT,
            ])
            ->with([self::WITH_VARIANT_PRODUCT])
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
