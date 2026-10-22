<?php

return [
    'description' => 'Pay via Airtel Money mobile money. Enter your phone number to receive a payment request.',
    'title' => 'Airtel Money',

    'redirect' => [
        'click-if-not-redirected' => 'Click here to continue',
        'pay-button' => 'Pay with Airtel Money',
        'phone-placeholder' => 'Enter your Airtel Money phone number (e.g. 0330000000)',
        'processing' => 'Processing...',
        'secure-payment' => 'Secure Payment',
        'step-1' => 'Enter your Airtel Money phone number.',
        'step-2' => 'A USSD push will be sent to your mobile device.',
        'step-3' => 'Enter your Airtel Money PIN to authorize the payment.',
    ],

    'response' => [
        'cart-not-found' => 'Cart not found or invalid.',
        'invalid-transaction' => 'Invalid transaction. Please try again.',
        'order-creation-failed' => 'The payment succeeded but the order could not be created. Please contact support.',
        'payment-cancelled' => 'The payment was cancelled.',
        'payment-failed' => 'The payment failed. Please try again.',
        'payment-pending' => 'Your Airtel Money payment is still pending. Please complete it on your mobile device or wait a moment and check again.',
        'payment-success' => 'The payment completed successfully.',
        'provide-credentials' => 'Please provide valid Airtel Money credentials in the admin panel.',
        'supported-currency-error' => 'The currency :currency is not supported. Supported Currencies: :supportedCurrencies.',
        'verification-failed' => 'The payment could not be verified.',
    ],
];
