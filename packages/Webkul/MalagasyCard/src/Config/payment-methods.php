<?php

use Webkul\MalagasyCard\Payment\MalagasyCardPayment;

return [
    'malagasy_card' => [
        'code' => 'malagasy_card',
        'title' => 'Malagasy Card',
        'description' => 'Card payment integration pending provider and merchant verification.',
        'class' => MalagasyCardPayment::class,
        'active' => false,
        'sandbox' => true,
        'api_key' => env('PAPI_API_KEY'),
        'merchant_id' => env('PAPI_MERCHANT_ID'),
        'webhook_secret' => env('PAPI_WEBHOOK_SECRET'),
        'sort' => 13,
    ],
];
