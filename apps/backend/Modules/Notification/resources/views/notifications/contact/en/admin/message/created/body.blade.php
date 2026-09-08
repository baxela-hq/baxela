<h1>{{ config('app.name') }}</h1>

<p>A new message was submitted through the contact form:</p>

<p style="font-size: 18px; font-weight: bold;">{{ $subject }}</p>

<p>
    <strong>From:</strong> {{ $name }} &lt;{{ $email }}&gt;
@if ($phone)
    &middot; {{ $phone }}
@endif
</p>

<p style="white-space: pre-wrap;">{{ $content }}</p>
