<h1>{{ config('app.name') }}</h1>

<p>عمق صف پیکربندی‌شده از آستانه هشدار گذشت:</p>

<ul dir="rtl">
    <li><strong>صف:</strong> {{ $queue }} (اتصال: {{ $connection }})</li>
    <li><strong>عمق:</strong> {{ $size }} کار (آستانه: {{ $threshold }})</li>
    <li><strong>زمان تشخیص:</strong> {{ $detected_at }}</li>
</ul>

<p>بررسی کارهای در انتظار در بخش <strong>مدیریت ← کارهای ناموفق</strong>.</p>
