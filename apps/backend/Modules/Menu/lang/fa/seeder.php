<?php

return [
    'header' => [
        'title' => 'منوی اصلی',
        'description' => 'ناوبری اصلی فروشگاه',
        'links' => [
            'home' => [
                'url' => '/',
                'translations' => [['title' => 'خانه']],
                'children' => [],
            ],
            'products' => [
                'url' => '/products',
                'translations' => [['title' => 'محصولات']],
                'children' => [
                    'sneakers' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'کفش ورزشی']],
                        'children' => [
                            'sneakers-low-top' => [
                                'url' => '/products?category=sneakers',
                                'translations' => [['title' => 'ساق کوتاه']],
                            ],
                            'sneakers-high-top' => [
                                'url' => '/products?category=sneakers',
                                'translations' => [['title' => 'ساق بلند']],
                            ],
                            'sneakers-runners' => [
                                'url' => '/products?category=sneakers',
                                'translations' => [['title' => 'رانینگ']],
                            ],
                        ],
                    ],
                    'apparel' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'پوشاک']],
                        'children' => [
                            'apparel-tees' => [
                                'url' => '/products?category=apparel',
                                'translations' => [['title' => 'تی‌شرت']],
                            ],
                            'apparel-hoodies' => [
                                'url' => '/products?category=apparel',
                                'translations' => [['title' => 'هودی']],
                            ],
                            'apparel-joggers' => [
                                'url' => '/products?category=apparel',
                                'translations' => [['title' => 'شلوار ورزشی']],
                            ],
                        ],
                    ],
                    'accessories' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'لوازم جانبی']],
                        'children' => [
                            'accessories-caps' => [
                                'url' => '/products?category=accessories',
                                'translations' => [['title' => 'کلاه']],
                            ],
                            'accessories-bags' => [
                                'url' => '/products?category=accessories',
                                'translations' => [['title' => 'کیف']],
                            ],
                            'accessories-socks' => [
                                'url' => '/products?category=accessories',
                                'translations' => [['title' => 'جوراب']],
                            ],
                        ],
                    ],
                    'footwear' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'کفش']],
                        'children' => [
                            'footwear-slip-ons' => [
                                'url' => '/products?category=footwear',
                                'translations' => [['title' => 'اسلیپ‌ان']],
                            ],
                            'footwear-sandals' => [
                                'url' => '/products?category=footwear',
                                'translations' => [['title' => 'صندل']],
                            ],
                            'footwear-boots' => [
                                'url' => '/products?category=footwear',
                                'translations' => [['title' => 'نیم‌بوت']],
                            ],
                        ],
                    ],
                ],
            ],
            'about' => [
                'url' => '/pages/about-us',
                'translations' => [['title' => 'درباره ما']],
                'children' => [],
            ],
            'contact' => [
                'url' => '/contact',
                'translations' => [['title' => 'تماس با ما']],
                'children' => [],
            ],
        ],
    ],

    'footer' => [
        'title' => 'منوی فوتر',
        'description' => 'ناوبری فوتر فروشگاه',
        'links' => [
            'help' => [
                'url' => '/contact',
                'translations' => [['title' => 'راهنما']],
                'children' => [
                    'contact' => [
                        'url' => '/contact',
                        'translations' => [['title' => 'تماس با ما']],
                    ],
                    'about' => [
                        'url' => '/pages/about-us',
                        'translations' => [['title' => 'درباره ما']],
                    ],
                    'faq' => [
                        'url' => '/pages/faq',
                        'translations' => [['title' => 'سوالات متداول']],
                    ],
                    'shipping' => [
                        'url' => '/pages/shipping-delivery',
                        'translations' => [['title' => 'ارسال و تحویل']],
                    ],
                    'returns' => [
                        'url' => '/pages/returns-refunds',
                        'translations' => [['title' => 'بازگشت و بازپرداخت']],
                    ],
                ],
            ],
            'legal' => [
                'url' => '/',
                'translations' => [['title' => 'قانونی']],
                'children' => [
                    'privacy-policy' => [
                        'url' => '/pages/privacy-policy',
                        'translations' => [['title' => 'سیاست حریم خصوصی']],
                    ],
                    'terms-of-service' => [
                        'url' => '/pages/terms-of-service',
                        'translations' => [['title' => 'قوانین و شرایط استفاده']],
                    ],
                ],
            ],
            'account' => [
                'url' => '/login',
                'translations' => [['title' => 'حساب کاربری']],
                'children' => [
                    'sign-in' => [
                        'url' => '/login',
                        'translations' => [['title' => 'ورود']],
                    ],
                    'sign-up' => [
                        'url' => '/signup',
                        'translations' => [['title' => 'ثبت‌نام']],
                    ],
                    'forgot-password' => [
                        'url' => '/forgot-password',
                        'translations' => [['title' => 'بازیابی رمز عبور']],
                    ],
                ],
            ],
        ],
    ],
];
