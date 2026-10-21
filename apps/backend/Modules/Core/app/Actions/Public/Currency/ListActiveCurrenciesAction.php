<?php

namespace Modules\Core\Actions\Public\Currency;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Schemas\Currency\CurrencySchema;

class ListActiveCurrenciesAction
{
    public function __construct(protected CoreGatewayInterface $coreGateway) {}

    public function handle(): Collection
    {
        return $this->coreGateway->getActiveCurrencies();
    }
}
