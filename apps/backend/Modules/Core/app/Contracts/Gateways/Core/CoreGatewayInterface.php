<?php

namespace Modules\Core\Contracts\Gateways\Core;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Contracts\Gateways\Core\DTOs\CurrencyDto;
use Modules\Core\Contracts\Gateways\Core\DTOs\LanguageDto;

interface CoreGatewayInterface
{
    public function getDefaultLanguage(): ?LanguageDto;

    public function getDefaultCurrency(): ?CurrencyDto;

    public function getLanguageIdByCode(string $code): ?int;

    public function getLanguageIdsByCodes(array $codes): array;

    public function getActiveLanguages(): Collection;
}
