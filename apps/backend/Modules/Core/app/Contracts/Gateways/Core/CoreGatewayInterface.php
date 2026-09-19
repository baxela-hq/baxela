<?php

namespace Modules\Core\Contracts\Gateways\Core;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Contracts\Gateways\Core\DTOs\CurrencyDto;
use Modules\Core\Contracts\Gateways\Core\DTOs\LanguageDto;

interface CoreGatewayInterface
{
    public function getDefaultLanguage(): ?LanguageDto;

    public function getDefaultCurrency(): ?CurrencyDto;

    public function getCurrencyById(int $id): ?CurrencyDto;

    /**
     * Flip the default flag to the given record, clearing every other row —
     * keeps flag-based consumers in sync with the admin settings.
     */
    public function markLanguageDefault(int $languageId): void;

    public function markCurrencyDefault(int $currencyId): void;

    public function getLanguageIdByCode(string $code): ?int;

    public function getLanguageIdsByCodes(array $codes): array;

    public function getActiveLanguages(): Collection;
}
