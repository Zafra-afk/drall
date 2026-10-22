<?php

use Webkul\Stripe\Payment\Stripe;

return [
    'stripe' => [
        'class' => Stripe::class,
        'code' => 'stripe',
        'title' => 'Stripe',
        'description' => 'Pay via Stripe',
        'active' => false,
        'sandbox' => true,
        'api_test_key' => 'API_TEST_KEY',
        'api_test_publishable_key' => 'API_TEST_PUBLISHABLE_KEY',
        'sort' => 1,
    ],
];
