<?php

namespace Modules\Cart\Transformers\User\Tax;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxResult;
use Modules\Core\Contracts\Gateways\Tax\DTOs\TaxedLine;
use Modules\Core\Support\Money;

class TaxPreviewResource extends JsonResource
{
    /**
     * @param  array{result: TaxResult, currency_id: ?int, shipping_tax_included: bool}  $resource
     */
    public function toArray(Request $request): array
    {
        $result = $this->resource['result'];

        return [
            // decimal strings — the exact amounts the order would persist
            'tax_amount' => Money::toDecimal($result->total_minor),
            'shipping_tax_included' => $this->resource['shipping_tax_included'],
            'shipping_tax_amount' => Money::toDecimal($result->shipping_tax_minor),
            'currency_id' => $this->resource['currency_id'],
            'lines' => collect($result->lines)
                ->map(fn (TaxedLine $line, $variantId): array => [
                    'variant_id' => (int) $variantId,
                    'tax_amount' => Money::toDecimal($line->tax_minor),
                    'rate' => $line->rate,
                    'tax_class_id' => $line->tax_class_id,
                ])
                ->values()
                ->all(),
        ];
    }
}
