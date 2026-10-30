<?php

return [
    'description' => 'Pay via MVola mobile money (Telma). Enter your phone number to receive a payment request.',
    'payment-description' => 'Payment for order #:cart_id',
    'title' => 'MVola',

    'redirect' => [
        'click-if-not-redirected' => 'Click here to continue',
        'pay-button' => 'Pay with MVola',
        'phone-placeholder' => 'Enter your MVola phone number (e.g. 0340000000)',
        'processing' => 'Processing...',
        'secure-payment' => 'Secure Payment',
        'step-1' => 'Enter your MVola phone number.',
        'step-2' => 'A payment request will be sent to your mobile.',
        'step-3' => 'Authorize the payment using your MVola PIN.',
    ],

    'response' => [
        'cart-not-found' => 'Cart not found or invalid.',
        'invalid-transaction' => 'Invalid transaction. Please try again.',
        'order-creation-failed' => 'The payment succeeded but the order could not be created. Please contact support.',
        'payment-cancelled' => 'The payment was cancelled.',
        'payment-failed' => 'The payment failed. Please try again.',
        'payment-pending' => 'Your MVola payment is still pending. Please complete it on your mobile device or wait a moment and check again.',
        'payment-success' => 'The payment completed successfully.',
        'provide-credentials' => 'Please provide valid MVola credentials in the admin panel.',
        'supported-currency-error' => 'The currency :currency is not supported. Supported Currencies: :supportedCurrencies.',
        'verification-failed' => 'The payment could not be verified.',
    ],
];
