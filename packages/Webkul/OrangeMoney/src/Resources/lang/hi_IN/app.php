<?php

return [
    'description' => 'Pay via Orange Money mobile money. Enter your phone number to receive a payment request.',
    'title' => 'Orange Money',

    'redirect' => [
        'click-if-not-redirected' => 'Click here to continue',
        'pay-button' => 'Pay with Orange Money',
        'phone-placeholder' => 'Enter your Orange Money phone number (e.g. 0320000000)',
        'processing' => 'Processing...',
        'secure-payment' => 'Secure Payment',
        'step-1' => 'Enter your Orange Money phone number.',
        'step-2' => 'A payment code will be sent to your mobile via USSD.',
        'step-3' => 'Enter the code to confirm your payment.',
    ],

    'response' => [
        'cart-not-found' => 'Cart not found or invalid.',
        'invalid-transaction' => 'Invalid transaction. Please try again.',
        'order-creation-failed' => 'The payment succeeded but the order could not be created. Please contact support.',
        'payment-cancelled' => 'The payment was cancelled.',
        'payment-failed' => 'The payment failed. Please try again.',
        'payment-pending' => 'Your Orange Money payment is still pending. Please complete it on your mobile device or wait a moment and check again.',
        'payment-success' => 'The payment completed successfully.',
        'provide-credentials' => 'Please provide valid Orange Money credentials in the admin panel.',
        'supported-currency-error' => 'The currency :currency is not supported. Supported Currencies: :supportedCurrencies.',
        'verification-failed' => 'The payment could not be verified.',
    ],
];
