<?php

return [
    'process.invalid_order' => 'الطلب غير صالح أو منتهي الصلاحية أو غير قابل للسداد',
    'process.method_not_supported' => 'طريقة الدفع هذه غير مدعومة بعد',
    'process.method_inactive' => 'طريقة الدفع هذه غير متاحة حاليًا',
    'process.gateway_unconfigured' => 'لم يتم إعداد طريقة الدفع هذه في المتجر بعد',
    'update.invalid_status_transition' => 'لم يعد بالإمكان تغيير حالة الدفع',
    'webhook.not_supported' => 'لا يقبل محرك الدفع هذا إشعارات webhook',
    'webhook.invalid' => 'لا تتطابق بيانات webhook مع أي دفعة',
    'refund.not_supported' => 'طريقة الدفع هذه لا تدعم الاسترداد بعد',
    'refund.exceeds_refundable' => 'مبلغ الاسترداد يتجاوز الرصيد القابل للاسترداد المتبقي',
    'refund.invalid_status' => 'الدفعة أو الاسترداد ليست في حالة تسمح بهذا الإجراء',
    'refund.failed' => 'رفضت البوابة الإلكترونية عملية الاسترداد',
    'refund.outcome_unknown' => 'نتيجة الاسترداد غير معروفة؛ وهي محفوظة للتسوية',
];
