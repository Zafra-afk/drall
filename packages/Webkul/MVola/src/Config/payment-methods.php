<?php

use Webkul\MVola\Payment\MVolaPayment;

return [
    'mvola' => [
        'code' => 'mvola',
        'title' => 'MVola',
        'class' => MVolaPayment::class,
        'active' => env('MVOLA_ACTIVE', false),
        'sandbox' => env('MVOLA_SANDBOX', true),
        'consumer_key' => env('MVOLA_CONSUMER_KEY'),
        'consumer_secret' => env('MVOLA_CONSUMER_SECRET'),
        'merchant_number' => env('MVOLA_MERCHANT_NUMBER'),
        'partner_name' => env('MVOLA_PARTNER_NAME'),
        'webhook_secret' => env('MVOLA_WEBHOOK_SECRET'),
        'sort' => 10,
    ],
];
