<?php

return [

    /*
    |--------------------------------------------------------------------------
    | پیام‌های اعتبارسنجی
    |--------------------------------------------------------------------------
    |
    | پیام‌های زیر شامل پیام‌های خطای پیش‌فرض هستند که توسط کلاس اعتبارسنجی
    | استفاده می‌شوند. برخی از این قوانین نسخه‌های مختلفی دارند، مانند
    | قوانین اندازه. می‌توانید هر یک از این پیام‌ها را در اینجا تنظیم کنید.
    |
    */

    'accepted' => 'فیلد :attribute باید پذیرفته شود.',
    'accepted_if' => 'وقتی :other برابر :value است، فیلد :attribute باید پذیرفته شود.',
    'active_url' => 'فیلد :attribute یک نشانی وب معتبر نیست.',
    'after' => 'فیلد :attribute باید تاریخی بعد از :date باشد.',
    'after_or_equal' => 'فیلد :attribute باید تاریخی بعد یا برابر :date باشد.',
    'alpha' => 'فیلد :attribute باید تنها شامل حروف باشد.',
    'alpha_dash' => 'فیلد :attribute باید تنها شامل حروف، اعداد، خط تیره و زیرخط باشد.',
    'alpha_num' => 'فیلد :attribute باید تنها شامل حروف و اعداد باشد.',
    'any_of' => 'فیلد :attribute نامعتبر است.',
    'array' => 'فیلد :attribute باید آرایه باشد.',
    'ascii' => 'فیلد :attribute باید تنها شامل نویسه‌های الفبایی و نمادهای تک‌بایتی باشد.',
    'before' => 'فیلد :attribute باید تاریخی قبل از :date باشد.',
    'before_or_equal' => 'فیلد :attribute باید تاریخی قبل یا برابر :date باشد.',
    'between' => [
        'array' => 'فیلد :attribute باید بین :min و :max آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید بین :min و :max کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بین :min و :max باشد.',
        'string' => 'فیلد :attribute باید بین :min و :max نویسه باشد.',
    ],
    'boolean' => 'فیلد :attribute باید true یا false باشد.',
    'can' => 'فیلد :attribute حاوی یک مقدار غیرمجاز است.',
    'confirmed' => 'تأییدیه فیلد :attribute مطابقت ندارد.',
    'contains' => 'فیلد :attribute فاقد یک مقدار مورد نیاز است.',
    'current_password' => 'رمز عبور نادرست است.',
    'date' => 'فیلد :attribute یک تاریخ معتبر نیست.',
    'date_equals' => 'فیلد :attribute باید تاریخی برابر :date باشد.',
    'date_format' => 'فیلد :attribute با قالب :format مطابقت ندارد.',
    'decimal' => 'فیلد :attribute باید :decimal رقم اعشار داشته باشد.',
    'declined' => 'فیلد :attribute باید رد شود.',
    'declined_if' => 'وقتی :other برابر :value است، فیلد :attribute باید رد شود.',
    'different' => 'فیلد :attribute و :other باید با هم متفاوت باشند.',
    'digits' => 'فیلد :attribute باید :digits رقم باشد.',
    'digits_between' => 'فیلد :attribute باید بین :min و :max رقم داشته باشد.',
    'dimensions' => 'ابعاد تصویر فیلد :attribute نامعتبر است.',
    'distinct' => 'فیلد :attribute مقدار تکراری دارد.',
    'doesnt_contain' => 'فیلد :attribute نباید شامل هیچ‌یک از موارد زیر باشد: :values.',
    'doesnt_end_with' => 'فیلد :attribute نباید به یکی از موارد زیر ختم شود: :values.',
    'doesnt_start_with' => 'فیلد :attribute نباید با یکی از موارد زیر شروع شود: :values.',
    'email' => 'فیلد :attribute باید یک نشانی ایمیل معتبر باشد.',
    'encoding' => 'فیلد :attribute باید با :encoding رمزگذاری شده باشد.',
    'ends_with' => 'فیلد :attribute باید به یکی از موارد زیر ختم شود: :values.',
    'enum' => ':attribute انتخاب‌شده نامعتبر است.',
    'exists' => ':attribute انتخاب‌شده نامعتبر است.',
    'extensions' => 'فیلد :attribute باید یکی از پسوندهای زیر را داشته باشد: :values.',
    'file' => 'فیلد :attribute باید یک فایل باشد.',
    'filled' => 'فیلد :attribute باید دارای مقدار باشد.',
    'gt' => [
        'array' => 'فیلد :attribute باید بیشتر از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید بزرگتر از :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بزرگتر از :value باشد.',
        'string' => 'فیلد :attribute باید بیشتر از :value نویسه داشته باشد.',
    ],
    'gte' => [
        'array' => 'فیلد :attribute باید :value آیتم یا بیشتر داشته باشد.',
        'file' => 'فیلد :attribute باید بزرگتر یا برابر :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید بزرگتر یا برابر :value باشد.',
        'string' => 'فیلد :attribute باید بیشتر یا برابر :value نویسه داشته باشد.',
    ],
    'hex_color' => 'فیلد :attribute باید یک رنگ هگزادسیمال معتبر باشد.',
    'image' => 'فیلد :attribute باید یک تصویر باشد.',
    'in' => ':attribute انتخاب‌شده نامعتبر است.',
    'in_array' => 'فیلد :attribute باید در :other وجود داشته باشد.',
    'in_array_keys' => 'فیلد :attribute باید شامل حداقل یکی از کلیدهای زیر باشد: :values.',
    'integer' => 'فیلد :attribute باید یک عدد صحیح باشد.',
    'ip' => 'فیلد :attribute باید یک نشانی IP معتبر باشد.',
    'ipv4' => 'فیلد :attribute باید یک نشانی IPv4 معتبر باشد.',
    'ipv6' => 'فیلد :attribute باید یک نشانی IPv6 معتبر باشد.',
    'json' => 'فیلد :attribute باید یک رشته JSON معتبر باشد.',
    'list' => 'فیلد :attribute باید یک لیست باشد.',
    'lowercase' => 'فیلد :attribute باید با حروف کوچک باشد.',
    'lt' => [
        'array' => 'فیلد :attribute باید کمتر از :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید کمتر از :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید کمتر از :value باشد.',
        'string' => 'فیلد :attribute باید کمتر از :value نویسه داشته باشد.',
    ],
    'lte' => [
        'array' => 'فیلد :attribute باید کمتر یا برابر :value آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید کمتر یا برابر :value کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید کمتر یا برابر :value باشد.',
        'string' => 'فیلد :attribute باید کمتر یا برابر :value نویسه داشته باشد.',
    ],
    'mac_address' => 'فیلد :attribute باید یک نشانی MAC معتبر باشد.',
    'max' => [
        'array' => 'فیلد :attribute نباید بیشتر از :max آیتم داشته باشد.',
        'file' => 'فیلد :attribute نباید بزرگتر از :max کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute نباید بزرگتر از :max باشد.',
        'string' => 'فیلد :attribute نباید بیشتر از :max نویسه داشته باشد.',
    ],
    'max_digits' => 'فیلد :attribute نباید بیشتر از :max رقم داشته باشد.',
    'mimes' => 'فیلد :attribute باید یک فایل از نوع: :values باشد.',
    'mimetypes' => 'فیلد :attribute باید یک فایل از نوع: :values باشد.',
    'min' => [
        'array' => 'فیلد :attribute باید حداقل :min آیتم داشته باشد.',
        'file' => 'فیلد :attribute باید حداقل :min کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید حداقل :min باشد.',
        'string' => 'فیلد :attribute باید حداقل :min نویسه داشته باشد.',
    ],
    'min_digits' => 'فیلد :attribute باید حداقل :min رقم داشته باشد.',
    'missing' => 'فیلد :attribute باید وجود نداشته باشد.',
    'missing_if' => 'وقتی :other برابر :value است، فیلد :attribute باید وجود نداشته باشد.',
    'missing_unless' => 'فیلد :attribute باید وجود نداشته باشد مگر اینکه :other برابر :value باشد.',
    'missing_with' => 'فیلد :attribute باید وجود نداشته باشد وقتی :values وجود دارد.',
    'missing_with_all' => 'فیلد :attribute باید وجود نداشته باشد وقتی :values همه وجود دارند.',
    'multiple_of' => 'فیلد :attribute باید مضربی از :value باشد.',
    'not_in' => ':attribute انتخاب‌شده نامعتبر است.',
    'not_regex' => 'فرمت فیلد :attribute نامعتبر است.',
    'numeric' => 'فیلد :attribute باید عدد باشد.',
    'password' => [
        'letters' => 'فیلد :attribute باید حداقل یک حرف داشته باشد.',
        'mixed' => 'فیلد :attribute باید حداقل یک حرف بزرگ و یک حرف کوچک داشته باشد.',
        'numbers' => 'فیلد :attribute باید حداقل یک عدد داشته باشد.',
        'symbols' => 'فیلد :attribute باید حداقل یک نماد داشته باشد.',
        'uncompromised' => ':attribute داده‌شده در یک نشتی داده ظاهر شده است. لطفاً :attribute دیگری انتخاب کنید.',
    ],
    'present' => 'فیلد :attribute باید وجود داشته باشد.',
    'present_if' => 'وقتی :other برابر :value است، فیلد :attribute باید وجود داشته باشد.',
    'present_unless' => 'فیلد :attribute باید وجود داشته باشد مگر اینکه :other برابر :value باشد.',
    'present_with' => 'فیلد :attribute باید وجود داشته باشد وقتی :values وجود دارد.',
    'present_with_all' => 'فیلد :attribute باید وجود داشته باشد وقتی :values همه وجود دارند.',
    'prohibited' => 'فیلد :attribute ممنوع است.',
    'prohibited_if' => 'وقتی :other برابر :value است، فیلد :attribute ممنوع است.',
    'prohibited_if_accepted' => 'وقتی :other پذیرفته شده است، فیلد :attribute ممنوع است.',
    'prohibited_if_declined' => 'وقتی :other رد شده است، فیلد :attribute ممنوع است.',
    'prohibited_unless' => 'فیلد :attribute ممنوع است مگر اینکه :other در :values باشد.',
    'prohibits' => 'فیلد :attribute مانع حضور :other است.',
    'regex' => 'فرمت فیلد :attribute نامعتبر است.',
    'required' => 'فیلد :attribute الزامی است.',
    'required_array_keys' => 'فیلد :attribute باید شامل ورودی‌هایی برای: :values باشد.',
    'required_if' => 'وقتی :other برابر :value است، فیلد :attribute الزامی است.',
    'required_if_accepted' => 'وقتی :other پذیرفته شده است، فیلد :attribute الزامی است.',
    'required_if_declined' => 'وقتی :other رد شده است، فیلد :attribute الزامی است.',
    'required_unless' => 'فیلد :attribute الزامی است مگر اینکه :other در :values باشد.',
    'required_with' => 'وقتی :values وجود دارد، فیلد :attribute الزامی است.',
    'required_with_all' => 'وقتی :values همه وجود دارند، فیلد :attribute الزامی است.',
    'required_without' => 'وقتی :values وجود ندارد، فیلد :attribute الزامی است.',
    'required_without_all' => 'وقتی هیچ‌یک از :values وجود ندارند، فیلد :attribute الزامی است.',
    'same' => 'فیلد :attribute باید با :other مطابقت داشته باشد.',
    'size' => [
        'array' => 'فیلد :attribute باید شامل :size آیتم باشد.',
        'file' => 'فیلد :attribute باید :size کیلوبایت باشد.',
        'numeric' => 'فیلد :attribute باید :size باشد.',
        'string' => 'فیلد :attribute باید :size نویسه داشته باشد.',
    ],
    'starts_with' => 'فیلد :attribute باید با یکی از موارد زیر شروع شود: :values.',
    'string' => 'فیلد :attribute باید یک رشته باشد.',
    'timezone' => 'فیلد :attribute باید یک منطقه زمانی معتبر باشد.',
    'unique' => ':attribute قبلاً انتخاب شده است.',
    'uploaded' => 'بارگذاری فیلد :attribute ناموفق بود.',
    'uppercase' => 'فیلد :attribute باید با حروف بزرگ باشد.',
    'url' => 'فیلد :attribute باید یک نشانی وب معتبر باشد.',
    'ulid' => 'فیلد :attribute باید یک ULID معتبر باشد.',
    'uuid' => 'فیلد :attribute باید یک UUID معتبر باشد.',

    /*
    |--------------------------------------------------------------------------
    | پیام‌های اعتبارسنجی سفارشی
    |--------------------------------------------------------------------------
    |
    | در اینجا می‌توانید پیام‌های اعتبارسنجی سفارشی را برای ویژگی‌ها با استفاده
    | از قرارداد «attribute.rule» برای نام‌گذاری پیام‌ها مشخص کنید. این کار
    | تعیین یک پیام سفارشی خاص برای یک قانون ویژگی خاص را سریع می‌کند.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ویژگی‌های اعتبارسنجی سفارشی
    |--------------------------------------------------------------------------
    |
    | پیام‌های زیر برای جایگزینی جایگیر ویژگی ما با چیزی خوانا‌تر مانند
    | «آدرس ایمیل» به جای «ایمیل» استفاده می‌شوند. این کار به گویاتر شدن
    | پیام کمک می‌کند.
    |
    */

    'attributes' => [],

];