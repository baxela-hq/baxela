<h1>{{ config('app.name') }}</h1>

<p>A new support ticket was opened:</p>

<p style="font-size: 18px; font-weight: bold;">{{ $subject }}</p>

@if ($order_code)
    <p><strong>Related order:</strong> {{ $order_code }}</p>
@endif

<p style="white-space: pre-wrap;">{{ $body }}</p>
