<?php

return [
    'name' => env('RESTAURANT_NAME', 'Restaurant ABC'),
    'address' => env('RESTAURANT_ADDRESS', 'Jl. Example No. 123, Jakarta'),
    'phone' => env('RESTAURANT_PHONE', '021-12345678'),

    'tables' => [
        'default_count' => env('RESTAURANT_TABLES_COUNT', 10),
    ],

    'tax_percentage' => env('RESTAURANT_TAX_PERCENTAGE', 10),

    'cache_ttl' => env('RESTAURANT_CACHE_TTL', 3600),
];
