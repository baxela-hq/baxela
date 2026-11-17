<?php

namespace Modules\Core\Actions\Public\Currency;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;

class ListActiveCurrenciesAction
{
    public function __construct(protected CoreGatewayInterface $coreGateway) {}

    /** @return Collection<int, Model> */
    public function handle(): Collection
    {
        return $this->coreGateway->getActiveCurrencies();
    }
}
