<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',

    'tenant' => [
        'creation_failed' => 'تعذر إنشاء المتجر',
        'update_failed' => 'تعذر تحديث المتجر',
        'slug_taken' => 'عنوان المتجر هذا محجوز مسبقًا',
        'status_change_failed' => 'تعذر تغيير حالة المتجر',
        'deletion_failed' => 'تعذر حذف المتجر',
    ],

    'domain' => [
        'attachment_failed' => 'تعذر ربط النطاق بالمتجر',
        'release_failed' => 'تعذر تحرير النطاق',
        'in_use' => 'هذا النطاق ينتمي إلى متجر آخر',
        'already_attached' => 'هذا النطاق مرتبط بالمتجر بالفعل',
        'cooling_off' => 'تم تحرير هذا النطاق مؤخرًا ولا يمكن ربطه بمتجر آخر بعد',
    ],

    'onboarding' => [
        'limit_reached' => 'لديك بالفعل الحد الأقصى من المتاجر',
        'failed' => 'تعذر إنشاء المتجر',
    ],
];
