<h1>{{ config('app.name') }}</h1>

<p>Your order <strong>{{ $order_code }}</strong> has been cancelled.</p>

@if ($reason === 'expired')
<p>The order was cancelled because payment was not completed in time.</p>
@endif

<p>If you have any questions, feel free to contact our support team.</p>
