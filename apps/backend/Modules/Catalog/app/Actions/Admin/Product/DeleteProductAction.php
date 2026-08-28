<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Modules\Core\Contracts\Events\Catalog\ProductDeletedEvent;

class DeleteProductAction extends AbstractProductAction
{
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);
        $delete = $record->delete();

        if ($delete) {
            event(ProductDeletedEvent::fill($record->toArray()));
        }

        return $delete;
    }
}
