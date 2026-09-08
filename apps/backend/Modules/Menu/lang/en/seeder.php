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
                'url' => '/about',
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
            'sneakers' => [
                'url' => '/products?category=sneakers',
                'translations' => [['title' => 'Sneakers']],
                'children' => [
                    'footer-sneakers-low-top' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'Low top']],
                    ],
                    'footer-sneakers-high-top' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'High top']],
                    ],
                    'footer-sneakers-runners' => [
                        'url' => '/products?category=sneakers',
                        'translations' => [['title' => 'Runners']],
                    ],
                ],
            ],
            'apparel' => [
                'url' => '/products?category=apparel',
                'translations' => [['title' => 'Apparel']],
                'children' => [
                    'footer-apparel-tees' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'Tees']],
                    ],
                    'footer-apparel-hoodies' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'Hoodies']],
                    ],
                    'footer-apparel-joggers' => [
                        'url' => '/products?category=apparel',
                        'translations' => [['title' => 'Joggers']],
                    ],
                ],
            ],
            'accessories' => [
                'url' => '/products?category=accessories',
                'translations' => [['title' => 'Accessories']],
                'children' => [
                    'footer-accessories-caps' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'Caps']],
                    ],
                    'footer-accessories-bags' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'Bags']],
                    ],
                    'footer-accessories-socks' => [
                        'url' => '/products?category=accessories',
                        'translations' => [['title' => 'Socks']],
                    ],
                ],
            ],
            'footwear' => [
                'url' => '/products?category=footwear',
                'translations' => [['title' => 'Footwear']],
                'children' => [
                    'footer-footwear-slip-ons' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'Slip-ons']],
                    ],
                    'footer-footwear-sandals' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'Sandals']],
                    ],
                    'footer-footwear-boots' => [
                        'url' => '/products?category=footwear',
                        'translations' => [['title' => 'Boots']],
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
            'support' => [
                'url' => '/contact',
                'translations' => [['title' => 'Support']],
                'children' => [],
            ],
        ],
    ],
];
