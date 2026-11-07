<?php

return [
    '401' => 'غير مصرّح',
    '403' => 'محظور',
    '404' => 'المورد غير موجود',
    '422' => 'فشل التحقق من البيانات',
    '429' => 'طلبات كثيرة جدًا. يُرجى المحاولة لاحقًا.',
    '500' => 'حدث خطأ ما',
    'internal_server_error' => 'حدث خطأ ما',

    // Failed-jobs admin surface (gap-B3) — agent-drafted, pending owner review.
    'failed_jobs' => [
        'not_found' => 'المهمة الفاشلة غير موجودة',
        'search_too_short' => 'يجب أن يكون البحث في رسالة الخطأ :min أحرف على الأقل',
    ],
];
