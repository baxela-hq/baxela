<h1>{{ config('app.name') }}</h1>

<p>یک پیغام جدید از طریق فرم تماس با ما ارسال شد:</p>

<p style="font-size: 18px; font-weight: bold;">{{ $subject }}</p>

<p>
    <strong>فرستنده:</strong> {{ $name }} &lt;{{ $email }}&gt;
@if ($phone)
    &middot; {{ $phone }}
@endif
</p>

<p dir="rtl" style="white-space: pre-wrap;">{{ $content }}</p>
