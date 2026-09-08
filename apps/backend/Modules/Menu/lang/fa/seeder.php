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
                'url' => '/about',
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
            'sneakers' => [
                'url' => '/products?category=sneakers',
                'translations' => [['title' => 'کفش ورزشی']],
                'children' => [
                    'footer-sneakers-low-top' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'ساق کوتاه']],
                    ],
                    'footer-sneakers-high-top' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'ساق بلند']],
                    ],
                    'footer-sneakers-runners' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'رانینگ']],
                    ],
                ],
            ],
            'apparel' => [
                'url' => '/products?category=apparel',
                'translations' => [['title' => 'پوشاک']],
                'children' => [
                    'footer-apparel-tees' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'تی‌شرت']],
                    ],
                    'footer-apparel-hoodies' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'هودی']],
                    ],
                    'footer-apparel-joggers' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'شلوار ورزشی']],
                    ],
                ],
            ],
            'accessories' => [
                'url' => '/products?category=accessories',
                'translations' => [['title' => 'لوازم جانبی']],
                'children' => [
                    'footer-accessories-caps' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'کلاه']],
                    ],
                    'footer-accessories-bags' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'کیف']],
                    ],
                    'footer-accessories-socks' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'جوراب']],
                    ],
                ],
            ],
            'footwear' => [
                'url' => '/products?category=footwear',
                'translations' => [['title' => 'کفش']],
                'children' => [
                    'footer-footwear-slip-ons' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'اسلیپ‌ان']],
                    ],
                    'footer-footwear-sandals' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'صندل']],
                    ],
                    'footer-footwear-boots' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'نیم‌بوت']],
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
            'support' => [
                'url' => '/contact',
                'translations' => [['title' => 'پشتیبانی']],
                'children' => [],
            ],
        ],
    ],
];
