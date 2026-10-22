<?php

use Webkul\Razorpay\Payment\RazorpayPayment;

return [
    'razorpay' => [
        'class' => RazorpayPayment::class,
        'code' => 'razorpay',
        'title' => 'Razorpay',
        'description' => 'Pay via Razorpay',
        'active' => false,
        'sandbox' => true,
        'test_client_id' => 'TEST_CLIENT_ID',
        'test_client_secret' => 'TEST_CLIENT_SECRET',
        'sort' => 2,
    ],
];
