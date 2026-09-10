<?php

return [
    'header' => [
        'title' => 'Main Menu',
        'description' => 'Primary storefront navigation',
        'links' => [
            'home' => [
                'url' => '/',
                'translations' => [['title' => 'Home']],
                'children' => [],
            ],
            'products' => [
                'url' => '/products',
                'translations' => [['title' => 'Products']],
                'children' => [
                    'sneakers' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'Sneakers']],
                        'children' => [
                            'sneakers-low-top' => [
                                'url' => '/products?category=sneakers',
                                'translations' => [['title' => 'Low top']],
                            ],
                            'sneakers-high-top' => [
                                'url' => '/products?category=sneakers',
                                'translations' => [['title' => 'High top']],
                            ],
                            'sneakers-runners' => [
                                'url' => '/products?category=sneakers',
                                'translations' => [['title' => 'Runners']],
                            ],
                        ],
                    ],
                    'apparel' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'Apparel']],
                        'children' => [
                            'apparel-tees' => [
                                'url' => '/products?category=apparel',
                                'translations' => [['title' => 'Tees']],
                            ],
                            'apparel-hoodies' => [
                                'url' => '/products?category=apparel',
                                'translations' => [['title' => 'Hoodies']],
                            ],
                            'apparel-joggers' => [
                                'url' => '/products?category=apparel',
                                'translations' => [['title' => 'Joggers']],
                            ],
                        ],
                    ],
                    'accessories' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'Accessories']],
                        'children' => [
                            'accessories-caps' => [
                                'url' => '/products?category=accessories',
                                'translations' => [['title' => 'Caps']],
                            ],
                            'accessories-bags' => [
                                'url' => '/products?category=accessories',
                                'translations' => [['title' => 'Bags']],
                            ],
                            'accessories-socks' => [
                                'url' => '/products?category=accessories',
                                'translations' => [['title' => 'Socks']],
                            ],
                        ],
                    ],
                    'footwear' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'Footwear']],
                        'children' => [
                            'footwear-slip-ons' => [
                                'url' => '/products?category=footwear',
                                'translations' => [['title' => 'Slip-ons']],
                            ],
                            'footwear-sandals' => [
                                'url' => '/products?category=footwear',
                                'translations' => [['title' => 'Sandals']],
                            ],
                            'footwear-boots' => [
                                'url' => '/products?category=footwear',
                                'translations' => [['title' => 'Boots']],
                            ],
                        ],
                    ],
                ],
            ],
            'about' => [
                'url' => '/pages/about-us',
                'translations' => [['title' => 'About']],
                'children' => [],
            ],
            'contact' => [
                'url' => '/contact',
                'translations' => [['title' => 'Contact']],
                'children' => [],
            ],
        ],
    ],

    'footer' => [
        'title' => 'Footer Menu',
        'description' => 'Footer storefront navigation',
        'links' => [
            'help' => [
                'url' => '/contact',
                'translations' => [['title' => 'Help']],
                'children' => [
                    'contact' => [
                        'url' => '/contact',
                        'translations' => [['title' => 'Contact us']],
                    ],
                    'about' => [
                        'url' => '/pages/about-us',
                        'translations' => [['title' => 'About us']],
                    ],
                    'faq' => [
                        'url' => '/pages/faq',
                        'translations' => [['title' => 'FAQ']],
                    ],
                    'shipping' => [
                        'url' => '/pages/shipping-delivery',
                        'translations' => [['title' => 'Shipping & delivery']],
                    ],
                    'returns' => [
                        'url' => '/pages/returns-refunds',
                        'translations' => [['title' => 'Returns & refunds']],
                    ],
                ],
            ],
            'legal' => [
                'url' => '/',
                'translations' => [['title' => 'Legal']],
                'children' => [
                    'privacy-policy' => [
                        'url' => '/pages/privacy-policy',
                        'translations' => [['title' => 'Privacy policy']],
                    ],
                    'terms-of-service' => [
                        'url' => '/pages/terms-of-service',
                        'translations' => [['title' => 'Terms of service']],
                    ],
                ],
            ],
            'account' => [
                'url' => '/login',
                'translations' => [['title' => 'Account']],
                'children' => [
                    'sign-in' => [
                        'url' => '/login',
                        'translations' => [['title' => 'Sign in']],
                    ],
                    'sign-up' => [
                        'url' => '/signup',
                        'translations' => [['title' => 'Sign up']],
                    ],
                    'forgot-password' => [
                        'url' => '/forgot-password',
                        'translations' => [['title' => 'Forgot password']],
                    ],
                ],
            ],
        ],
    ],
];
