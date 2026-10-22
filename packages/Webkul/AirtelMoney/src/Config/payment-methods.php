<?php

use Webkul\AirtelMoney\Payment\AirtelMoneyPayment;

return [
    'airtel_money' => [
        'code' => 'airtel_money',
        'title' => 'Airtel Money',
        'class' => AirtelMoneyPayment::class,
        'active' => env('AIRTEL_MONEY_ACTIVE', false),
        'sandbox' => env('AIRTEL_MONEY_SANDBOX', true),
        'client_id' => env('AIRTEL_MONEY_CLIENT_ID'),
        'client_secret' => env('AIRTEL_MONEY_CLIENT_SECRET'),
        'merchant_id' => env('AIRTEL_MONEY_MERCHANT_ID'),
        'webhook_secret' => env('AIRTEL_MONEY_WEBHOOK_SECRET'),
        'sort' => 12,
    ],
];
