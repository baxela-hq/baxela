@php
    /** @var \Modules\Core\Contracts\Gateways\Order\DocumentView $doc */
    $rtl = $doc->isRtl();
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $money = fn ($value): string => number_format((float) $value, $doc->decimalPlaces).' '.$doc->currencyCode;
    $l = fn (string $key): string => __('order::documents.'.$key);
@endphp
<html lang="{{ $doc->locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans; font-size: 10pt; color: #1a1a1a; }
        h1 { font-size: 15pt; margin: 0; }
        h2 { font-size: 12pt; margin: 16pt 0 4pt; }
        .muted { color: #666; }
        .header { width: 100%; }
        .header td { vertical-align: top; width: 50%; }
        .block { margin: 6pt 0; line-height: 1.5; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8pt; }
        table.items th, table.items td { border: 0.5pt solid #bbb; padding: 4pt 6pt; font-size: 9pt; }
        table.items th { background: #f2f2f2; text-align: {{ $align }}; }
        table.totals { width: 45%; border-collapse: collapse; margin-top: 10pt; margin-inline-start: auto; }
        table.totals td { padding: 3pt 6pt; font-size: 10pt; }
        table.totals tr.grand td { border-top: 1pt solid #1a1a1a; font-weight: bold; padding-top: 5pt; }
        .number { font-size: 12pt; font-weight: bold; }
        .credit-amount { font-size: 14pt; font-weight: bold; margin-top: 8pt; }
        .footer { margin-top: 18pt; border-top: 0.5pt solid #ccc; padding-top: 6pt; font-size: 8pt; color: #888; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $doc->seller['name'] ?? '' }}</h1>
                <div class="block muted">
                    @if (! empty($doc->seller['address'])){{ $doc->seller['address'] }}<br>@endif
                    @if (! empty($doc->seller['tax_id'])){{ $l('tax_id') }}: {{ $doc->seller['tax_id'] }}<br>@endif
                    @if (! empty($doc->seller['contact'])){{ $doc->seller['contact'] }}@endif
                </div>
            </td>
            <td style="text-align: {{ $rtl ? 'left' : 'right' }};">
                <div class="number">{{ $doc->number }}</div>
                <div class="muted">{{ $doc->kind === 'credit_note' ? $l('title_credit_note') : $l('title_invoice') }}</div>
                <div class="muted">{{ $l('issue_date') }}: {{ $doc->issuedAt }}</div>
                @if (! empty($doc->meta['order_code']))<div class="muted">{{ $l('order_code') }}: {{ $doc->meta['order_code'] }}</div>@endif
            </td>
        </tr>
    </table>

    <h2>{{ $l('buyer') }}</h2>
    <div class="block">
        @if (! empty($doc->buyer['name'])){{ $doc->buyer['name'] }}<br>@endif
        @if (! empty($doc->buyer['vat_id'])){{ $l('vat_id') }}: {{ $doc->buyer['vat_id'] }}<br>@endif
        @if (! empty($doc->buyer['email'])){{ $doc->buyer['email'] }}<br>@endif
        @if (! empty($doc->billing['address_line'])){{ $doc->billing['address_line'] }}<br>@endif
        @if (! empty($doc->billing['city'])){{ $doc->billing['city'] }} @if (! empty($doc->billing['postal_code'])){{ $doc->billing['postal_code'] }}@endif<br>@endif
        @if (! empty($doc->billing['country_code'])){{ $doc->billing['country_code'] }}@endif
    </div>

@if ($doc->kind === 'credit_note')
    <h2>{{ $l('original_invoice') }}</h2>
    <div class="block">
        {{ $doc->totals['original_number'] ?? '' }}<br>
        @if (! empty($doc->totals['reason'])){{ $l('reason') }}: {{ $doc->totals['reason'] }}@endif
    </div>
    <div class="credit-amount">{{ $l('credited_amount') }}: {{ $money($doc->totals['amount'] ?? 0) }}</div>
@else
    <h2>{{ $l('items') }}</h2>
    <table class="items">
        <thead>
            <tr>
                <th>{{ $l('product') }}</th>
                <th>{{ $l('sku') }}</th>
                <th>{{ $l('qty') }}</th>
                <th>{{ $l('unit_price') }}</th>
                <th>{{ $l('discount') }}</th>
                <th>{{ $l('tax_rate') }}</th>
                <th>{{ $l('tax_amount') }}</th>
                <th>{{ $l('line_total') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($doc->items as $item)
            <tr>
                <td>{{ $item['product_name'] }}</td>
                <td>{{ $item['sku'] ?? '—' }}</td>
                <td>{{ $item['quantity'] }}</td>
                <td>{{ $money($item['unit_price']) }}</td>
                <td>{{ $money($item['discount_amount']) }}</td>
                <td>{{ $item['tax_rate'] !== null ? rtrim(rtrim(number_format((float) $item['tax_rate'], 2), '0'), '.').'%' : '—' }}</td>
                <td>{{ $money($item['tax_amount']) }}</td>
                <td>{{ $money($item['line_total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8">—</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>{{ $l('subtotal') }}</td><td style="text-align: {{ $rtl ? 'left' : 'right' }};">{{ $money($doc->totals['subtotal'] ?? 0) }}</td></tr>
        @if ((float) ($doc->totals['discount'] ?? 0) != 0.0)
            <tr><td>{{ $l('order_discount') }}@if (! empty($doc->totals['coupon_code'])) ({{ $doc->totals['coupon_code'] }})@endif</td><td style="text-align: {{ $rtl ? 'left' : 'right' }};">-{{ $money($doc->totals['discount']) }}</td></tr>
        @endif
        @if ((float) ($doc->totals['shipping'] ?? 0) != 0.0)
            <tr><td>{{ $l('shipping') }}</td><td style="text-align: {{ $rtl ? 'left' : 'right' }};">{{ $money($doc->totals['shipping']) }}</td></tr>
        @endif
        @if ((float) ($doc->totals['shipping_tax'] ?? 0) != 0.0)
            <tr><td>{{ $l('shipping_tax') }}</td><td style="text-align: {{ $rtl ? 'left' : 'right' }};">{{ $money($doc->totals['shipping_tax']) }}</td></tr>
        @endif
        <tr><td>{{ $l('tax_total') }}</td><td style="text-align: {{ $rtl ? 'left' : 'right' }};">{{ $money($doc->totals['tax'] ?? 0) }}</td></tr>
        <tr class="grand"><td>{{ $l('grand_total') }}</td><td style="text-align: {{ $rtl ? 'left' : 'right' }};">{{ $money($doc->totals['total'] ?? 0) }}</td></tr>
    </table>
    @if ($doc->pricesIncludeTax)
        <div class="muted" style="font-size: 8pt;">{{ $l('prices_include_tax_note') }}</div>
    @endif
@endif

    <div class="footer">{{ $doc->number }} · {{ $doc->issuedAt }}</div>
</body>
</html>
