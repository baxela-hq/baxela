<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',

    'tenant' => [
        'creation_failed' => 'فروشگاه ایجاد نشد',
        'update_failed' => 'فروشگاه به‌روزرسانی نشد',
        'slug_taken' => 'این آدرس فروشگاه قبلاً انتخاب شده است',
        'status_change_failed' => 'وضعیت فروشگاه تغییر نکرد',
        'deletion_failed' => 'فروشگاه حذف نشد',
    ],
    'domain' => [
        'attachment_failed' => 'دامنه به فروشگاه متصل نشد',
        'release_failed' => 'دامنه آزاد نشد',
        'in_use' => 'این دامنه متعلق به فروشگاه دیگری است',
        'already_attached' => 'این دامنه قبلاً به فروشگاه متصل شده است',
        'cooling_off' => 'این دامنه به‌تازگی آزاد شده است و هنوز نمی‌توان آن را به فروشگاه دیگری متصل کرد',
    ],
    'onboarding' => [
        'limit_reached' => 'شما به حداکثر تعداد فروشگاه مجاز رسیده‌اید',
        'failed' => 'فروشگاه ایجاد نشد',
    ],
];
