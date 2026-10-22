<?php

use Webkul\PhonePe\Payment\PhonePe;

return [
    'phonepe' => [
        'code' => 'phonepe',
        'title' => 'PhonePe',
        'description' => 'Pay via PhonePe',
        'class' => PhonePe::class,
        'active' => false,
        'sandbox' => true,
        'client_id' => 'CLIENT_ID',
        'client_secret' => 'CLIENT_SECRET',
        'merchant_id' => 'MERCHANT_ID',
        'sort' => 4,
    ],
];
