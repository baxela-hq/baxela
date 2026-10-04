<h1>{{ config('app.name') }}</h1>

<p>مشتری به تیکت پشتیبانی زیر پاسخ داد:</p>

<p style="font-size: 18px; font-weight: bold;" dir="rtl">{{ $subject }}</p>

@if ($order_code)
    <p><strong>سفارش مرتبط:</strong> <strong dir="rtl">{{ $order_code }}</strong></p>
@endif

<p style="white-space: pre-wrap;" dir="rtl">{{ $body }}</p>
