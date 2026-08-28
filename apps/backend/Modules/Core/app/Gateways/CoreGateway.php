<?php

namespace Modules\Core\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Core\DTOs\CurrencyDto;
use Modules\Core\Contracts\Gateways\Core\DTOs\LanguageDto;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Language;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Schemas\Language\LanguageSchema;

class CoreGateway implements CoreGatewayInterface
{
    public function getDefaultLanguage(): ?LanguageDto
    {
        $record = Language::query()->where(LanguageSchema::IS_DEFAULT, true)->first();

        if (! $record) {
            return null;
        }

        return LanguageDto::fill($record->toArray());
    }

    public function getDefaultCurrency(): ?CurrencyDto
    {
        $record = Currency::query()->where(CurrencySchema::IS_DEFAULT, true)->first();

        if (! $record) {
            return null;
        }

        return CurrencyDto::fill($record->toArray());
    }

    public function getLanguageIdByCode(string $code): ?int
    {
        $record = Language::query()->where(LanguageSchema::CODE, $code)->first();

        return $record?->{LanguageSchema::ID};
    }

    /**
     * @return array(<string, int>)
     */
    public function getLanguageIdsByCodes(array $codes): array
    {
        return Language::query()
            ->whereIn(LanguageSchema::CODE, $codes)
            ->pluck(LanguageSchema::ID, LanguageSchema::CODE)
            ->toArray();
    }

    public function getActiveLanguages(): Collection
    {
        return Language::query()->where(LanguageSchema::IS_ACTIVE, true)->get();
    }
}
