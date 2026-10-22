<?php

use Webkul\OrangeMoney\Payment\OrangeMoneyPayment;

return [
    'orange_money' => [
        'code' => 'orange_money',
        'title' => 'Orange Money',
        'class' => OrangeMoneyPayment::class,
        'active' => env('ORANGE_MONEY_ACTIVE', false),
        'sandbox' => env('ORANGE_MONEY_SANDBOX', true),
        'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
        'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
        'merchant_key' => env('ORANGE_MONEY_MERCHANT_KEY'),
        'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
        'sort' => 11,
    ],
];
