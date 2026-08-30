<?php

namespace Modules\Shipping\Actions\User\Method;

use Modules\Core\Contracts\Gateways\Shipping\DTOs\ShippingMethodQuoteDto;
use Modules\Core\Contracts\Gateways\Shipping\ShippingGatewayInterface;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;
use Modules\Core\Utils\Auth;
use Modules\Shipping\Exceptions\Method\InvalidAddressException;

class ListMethodAction
{
    public function __construct(
        protected UserGatewayInterface $userGateway,
        protected ShippingGatewayInterface $shippingGateway,
    ) {}

    /**
     * @return array<int, ShippingMethodQuoteDto>
     *
     * @throws InvalidAddressException
     */
    public function handle(int $addressId): array
    {
        $address = $this->userGateway->getAddress(Auth::id(), $addressId);
        if (is_null($address) || is_null($address->country_code)) {
            throw new InvalidAddressException;
        }

        return $this->shippingGateway->getMethodsForCountry($address->country_code);
    }
}
