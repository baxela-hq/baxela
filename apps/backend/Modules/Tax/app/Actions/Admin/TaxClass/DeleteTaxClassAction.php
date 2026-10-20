<?php

namespace Modules\Tax\Actions\Admin\TaxClass;

use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Tax\Exceptions\TaxClass\DeleteDefaultException;
use Modules\Tax\Exceptions\TaxClass\InUseException;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;

class DeleteTaxClassAction
{
    public function __construct(protected CatalogGatewayInterface $catalogGateway) {}

    /**
     * @throws DeleteDefaultException|InUseException
     */
    public function handle(string $id): bool
    {
        $record = TaxClass::query()->findOrFail($id);

        if ($record->{TaxClassSchema::IS_DEFAULT}) {
            throw new DeleteDefaultException;
        }

        // A class still assigned to products must not strand those products
        // on an unresolvable reference — that would fail checkout loudly
        if ($this->catalogGateway->countProductsByTaxClass((int) $record->{TaxClassSchema::ID}) > 0) {
            throw new InUseException;
        }

        return (bool) $record->delete();
    }
}
