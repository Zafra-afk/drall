<?php

use Webkul\PayGlocal\Payment\PayGlocal;

return [
    'payglocal' => [
        'code' => 'payglocal',
        'title' => 'PayGlocal',
        'description' => 'Pay via PayGlocal',
        'class' => PayGlocal::class,
        'active' => false,
        'sandbox' => env('PAYGLOCAL_SANDBOX', true),
        'merchant_id' => env('PAYGLOCAL_MERCHANT_ID'),
        'public_key_id' => env('PAYGLOCAL_PUBLIC_KEY_ID'),
        'private_key_id' => env('PAYGLOCAL_PRIVATE_KEY_ID'),
        'payglocal_public_key' => env('PAYGLOCAL_PUBLIC_KEY'),
        'merchant_private_key' => env('PAYGLOCAL_MERCHANT_PRIVATE_KEY'),
        'accepted_currencies' => env('PAYGLOCAL_CURRENCY', 'MGA'),
        'sort' => 9,
    ],
];
