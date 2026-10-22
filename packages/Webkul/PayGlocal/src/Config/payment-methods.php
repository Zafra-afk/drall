<?php

use Webkul\PayGlocal\Payment\PayGlocal;

return [
    'payglocal' => [
        'code' => 'payglocal',
        'title' => 'PayGlocal',
        'description' => 'Pay via PayGlocal',
        'class' => PayGlocal::class,
        'active' => false,
        'sandbox' => true,
        'merchant_id' => 'MERCHANT_ID',
        'public_key_id' => 'PUBLIC_KEY_ID',
        'private_key_id' => 'PRIVATE_KEY_ID',
        'payglocal_public_key' => 'PAYGLOCAL_PUBLIC_KEY',
        'merchant_private_key' => 'MERCHANT_PRIVATE_KEY',
        'accepted_currencies' => 'USD',
        'sort' => 9,
    ],
];
