<?php

namespace Modules\Shipping\Gateways;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Core\Contracts\Gateways\Shipping\DTOs\ShippingMethodQuoteDto;
use Modules\Core\Contracts\Gateways\Shipping\ShippingGatewayInterface;
use Modules\Core\Schemas\Country\CountrySchema;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Modules\Shipping\Schemas\Method\MethodTranslationSchema;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Modules\Shipping\Schemas\Zone\ZoneSchema;

class ShippingGateway implements ShippingGatewayInterface
{
    public function __construct(protected CoreGatewayInterface $coreGateway) {}

    public function getMethodsForCountry(string $countryCode): array
    {
        return $this->quotesForCountry($countryCode);
    }

    public function calculateCost(int $methodId, string $countryCode): ?float
    {
        return $this->getQuote($methodId, $countryCode)?->price;
    }

    public function getQuote(int $methodId, string $countryCode): ?ShippingMethodQuoteDto
    {
        foreach ($this->quotesForCountry($countryCode, $methodId) as $quote) {
            if ($quote->id === $methodId) {
                return $quote;
            }
        }

        return null;
    }

    /**
     * Active methods with a rate on any active zone matching the country.
     * Zones without countries act as a "rest of world" fallback; when several
     * zones match, the cheapest rate wins.
     *
     * @return array<int, ShippingMethodQuoteDto>
     */
    private function quotesForCountry(string $countryCode, ?int $methodId = null): array
    {
        $zoneIds = Zone::query()
            ->where(ZoneSchema::IS_ACTIVE, true)
            ->where(function (Builder $query) use ($countryCode) {
                $query
                    ->whereHas('countries', function (Builder $query) use ($countryCode) {
                        $query->where(CountrySchema::CODE, $countryCode);
                    })
                    ->orDoesntHave('countries');
            })
            ->pluck(ZoneSchema::ID);

        $query = Rate::query()
            ->whereIn(RateSchema::ZONE_ID, $zoneIds)
            ->whereHas('method', function (Builder $query) {
                $query->where(MethodSchema::IS_ACTIVE, true);
            })
            ->with('method.translations')
            ->when(! is_null($methodId), function (Builder $query) use ($methodId) {
                $query->where(RateSchema::METHOD_ID, $methodId);
            });

        $rates = $query->get()
            ->sortBy(RateSchema::PRICE)
            ->unique(RateSchema::METHOD_ID)
            ->values();

        $defaultLanguageId = $this->defaultLanguageId();

        $quotes = [];
        foreach ($rates as $rate) {
            $quotes[] = ShippingMethodQuoteDto::fill([
                'id' => $rate->{RateSchema::METHOD_ID},
                'name' => $this->methodName($rate->method, $defaultLanguageId),
                'price' => (float) $rate->{RateSchema::PRICE},
            ]);
        }

        return $quotes;
    }

    private function methodName(Method $method, ?int $defaultLanguageId): string
    {
        $translations = $method->translations;

        return $translations->firstWhere(MethodTranslationSchema::LANGUAGE_ID, $defaultLanguageId)
            ?->{MethodTranslationSchema::NAME}
            ?? $translations->first()?->{MethodTranslationSchema::NAME}
            ?? $method->{MethodSchema::CODE};
    }

    private function defaultLanguageId(): ?int
    {
        $language = $this->coreGateway->getDefaultLanguage();

        return is_null($language) ? null : (int) $language->id;
    }
}
