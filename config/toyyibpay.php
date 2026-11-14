<?php

return [
    'enabled' => (bool) env('TOYYIBPAY_ENABLED', false),
    'environment' => env('TOYYIBPAY_ENVIRONMENT', 'sandbox'),
    'sandbox_secret_key' => env('TOYYIBPAY_SANDBOX_SECRET_KEY', ''),
    'sandbox_category_code' => env('TOYYIBPAY_SANDBOX_CATEGORY_CODE', ''),
    'production_secret_key' => env('TOYYIBPAY_PRODUCTION_SECRET_KEY', ''),
    'production_category_code' => env('TOYYIBPAY_PRODUCTION_CATEGORY_CODE', ''),
];
