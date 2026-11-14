<?php

return [
    'process.invalid_order' => 'سفارش نامعتبر، منقضی شده یا قابل پرداخت نیست',
    'process.method_not_supported' => 'این روش پرداخت هنوز پشتیبانی نمی‌شود',
    'process.method_inactive' => 'این روش پرداخت در حال حاضر در دسترس نیست',
    'process.gateway_unconfigured' => 'این روش پرداخت هنوز در فروشگاه پیکربندی نشده است',
    'update.invalid_status_transition' => 'وضعیت این پرداخت دیگر قابل تغییر نیست',
    'webhook.not_supported' => 'این درایور پرداخت وب‌هوک ندارد',
    'webhook.invalid' => 'محتوای وب‌هوک با هیچ پرداختی مطابقت ندارد',
    'refund.not_supported' => 'این روش پرداخت هنوز از بازپرداخت پشتیبانی نمی‌کند',
    'refund.exceeds_refundable' => 'مبلغ بازپرداخت بیش از مانده قابل بازگشت است',
    'refund.invalid_status' => 'پرداخت یا بازپرداخت در وضعیتی نیست که این اقدام را مجاز کند',
    'refund.failed' => 'درگاه پرداخت بازپرداخت را رد کرد',
    'refund.outcome_unknown' => 'نتیجه بازپرداخت نامشخص است و برای بررسی نگه داشته شد',

    // Driver credential admin surface (marketplace P5.2) — see
    // DriverCredentialsException; values never appear in responses.
    'credentials' => [
        'driver_unknown' => 'درایور پرداخت :driver یک درایور قابل پیکربندی نیست',
        'key_unknown' => 'کلید اعتبارنامه :key برای درایور :driver تعریف نشده است',
    ],
];
