<?php

namespace Modules\Core\Actions\Public\Currency;

use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Core\DTOs\CurrencyDto;

class ShowDefaultCurrencyAction
{
    public function __construct(protected CoreGatewayInterface $gateway) {}

    public function handle(): ?CurrencyDto
    {
        return $this->gateway->getDefaultCurrency();
    }
}
