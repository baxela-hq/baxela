<?php

namespace Modules\Core\Contracts\Gateways\Order;

/**
 * The neutral document shape a renderer consumes — the boundary between
 * the Order module's document records and any PDF provider (B.1 D12).
 * Built by the Order module from its immutable snapshots; carries no
 * Eloquent models, so country-specific provider extensions (Factur-X,
 * Moadian, …) can consume it without touching Order internals.
 */
final class DocumentView
{
    /**
     * @param  string  $kind  invoice|credit_note
     * @param  array<string, mixed>  $seller  name, tax_id, address, contact
     * @param  array<string, mixed>  $buyer  name, email, vat_id
     * @param  array<string, mixed>  $billing  address lines
     * @param  array<string, mixed>  $meta  order_code, payment references
     * @param  list<array<string, mixed>>  $items  line items
     * @param  array<string, mixed>  $totals  subtotal…total; credit notes: amount, reason, original_number
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $number,
        public readonly string $issuedAt,
        public readonly string $locale,
        public readonly string $currencyCode,
        public readonly int $decimalPlaces,
        public readonly bool $pricesIncludeTax,
        public readonly array $seller,
        public readonly array $buyer,
        public readonly array $billing,
        public readonly array $meta,
        public readonly array $items,
        public readonly array $totals,
    ) {}

    public function isRtl(): bool
    {
        return in_array($this->locale, ['fa', 'ar'], true);
    }
}
