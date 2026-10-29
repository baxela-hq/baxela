<?php

namespace Modules\Tax\Gateways;

use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxCalculationInput;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxedLine;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxResult;
use Modules\Core\Contracts\Gateways\Tax\TaxGatewayInterface;
use Modules\Core\Exceptions\Tax\TaxConfigurationException;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Modules\Tax\Support\TaxMath;

class TaxGateway implements TaxGatewayInterface
{
    public function taxClassExists(int $taxClassId): bool
    {
        return TaxClass::query()->whereKey($taxClassId)->exists();
    }

    public function calculateForItems(TaxCalculationInput $input): TaxResult
    {
        // Classes are a handful of rows; rates are the country slice
        $classes = TaxClass::query()->get()->keyBy(TaxClassSchema::ID);

        $defaultClass = $classes->firstWhere(TaxClassSchema::IS_DEFAULT, true);
        if (is_null($defaultClass) && $input->items !== []) {
            throw new TaxConfigurationException('tax.configuration.no_default');
        }

        $rates = TaxRate::query()
            ->where(TaxRateSchema::COUNTRY_CODE, $input->country_code)
            ->where(TaxRateSchema::IS_ACTIVE, true)
            ->get()
            ->keyBy(TaxRateSchema::TAX_CLASS_ID);

        // Seller-funded coupon discount leaves the taxable base first —
        // allocated across the post-promotion line amounts
        $lineAmounts = [];
        foreach ($input->items as $item) {
            $lineAmounts[$item->variant_id] = $item->amount_minor;
        }

        $allocations = TaxMath::allocate($input->coupon_discount_minor, $lineAmounts);

        $lines = [];
        $totalMinor = 0;

        foreach ($input->items as $item) {
            $classId = $item->tax_class_id ?? (int) $defaultClass->{TaxClassSchema::ID};

            // A broken class reference is a data-integrity problem, not a
            // zero-tax configuration — refuse loudly
            if (! $classes->has($classId)) {
                throw new TaxConfigurationException('tax.configuration.invalid_class', meta: [
                    TaxRateSchema::TAX_CLASS_ID => $classId,
                ]);
            }

            $rate = $rates->get($classId);

            $base = max(0, $item->amount_minor - ($allocations[$item->variant_id] ?? 0));

            $taxMinor = is_null($rate)
                ? 0
                : TaxMath::taxOf($base, TaxMath::rateUnits((string) $rate->{TaxRateSchema::RATE}));

            $lines[$item->variant_id] = new TaxedLine(
                tax_minor: $taxMinor,
                rate: is_null($rate) ? '0.0000' : (string) $rate->{TaxRateSchema::RATE},
                tax_class_id: $classId,
            );

            $totalMinor += $taxMinor;
        }

        // Shipping is taxed only through the flagged class's country rate;
        // no class or no rate means untaxed shipping (valid configuration)
        $shippingClass = $classes->firstWhere(TaxClassSchema::IS_SHIPPING, true);
        $shippingRate = is_null($shippingClass)
            ? null
            : $rates->get((int) $shippingClass->{TaxClassSchema::ID});

        $shippingTaxMinor = ($input->shipping_cost_minor > 0 && ! is_null($shippingRate))
            ? TaxMath::taxOf(
                $input->shipping_cost_minor,
                TaxMath::rateUnits((string) $shippingRate->{TaxRateSchema::RATE})
            )
            : 0;

        return new TaxResult(
            total_minor: $totalMinor + $shippingTaxMinor,
            lines: $lines,
            shipping_tax_minor: $shippingTaxMinor,
            shipping_rate: is_null($shippingRate) ? null : (string) $shippingRate->{TaxRateSchema::RATE},
            shipping_tax_class_id: is_null($shippingRate) ? null : (int) $shippingClass->{TaxClassSchema::ID},
        );
    }
}
