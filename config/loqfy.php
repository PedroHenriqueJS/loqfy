<?php

return [
    'ai' => [
        'provider' => env('LOQFY_AI_PROVIDER', 'groq'),
        'model' => env('LOQFY_AI_MODEL', 'qwen/qwen3.8-27b'),
    ],

    'company' => [
        'name' => env('LOQFY_COMPANY_NAME', 'Locadora Demo Ltda.'),
        'cnpj' => env('LOQFY_COMPANY_CNPJ'),
        'address' => env('LOQFY_COMPANY_ADDRESS', 'Grande Florianópolis/SC'),
        'phone' => env('LOQFY_COMPANY_PHONE'),
        'city' => env('LOQFY_COMPANY_CITY', 'Florianópolis'),
        'late_fee_multiplier' => 1.0,
    ],

    'length_discounts' => [
        30 => 0.30,
        7 => 0.15,
    ],

    'inspection_photo_max_side' => 1024,
];
