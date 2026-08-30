<?php

namespace Modules\Core\Contracts\Gateways\Shipping;

use Modules\Core\Contracts\Gateways\Shipping\DTOs\ShippingMethodQuoteDto;

interface ShippingGatewayInterface
{
    /**
     * Active shipping methods available for the given country, with their price.
     *
     * @return array<int, ShippingMethodQuoteDto>
     */
    public function getMethodsForCountry(string $countryCode): array;

    /**
     * Cost of the given method for the given country — null when unavailable there.
     */
    public function calculateCost(int $methodId, string $countryCode): ?float;

    /**
     * Full quote (id, name, price) for one method — null when unavailable in the country.
     */
    public function getQuote(int $methodId, string $countryCode): ?ShippingMethodQuoteDto;
}
