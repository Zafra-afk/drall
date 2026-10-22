<?php

return [
    'description' => 'Pay securely with your credit or debit card via Papi (papi.mg). Supports Visa, MasterCard, and American Express.',
    'title' => 'Malagasy Card',

    'redirect' => [
        'click-if-not-redirected' => 'Click here to continue',
        'pay-button' => 'Pay with Card',
        'processing' => 'Processing...',
        'secure-payment' => 'Secure Payment Gateway',
        'info-1' => 'You will be redirected to Papi\'s secure payment page.',
        'info-2' => 'Enter your card details to complete the payment.',
        'info-3' => 'After payment, you will be redirected back to this store.',
    ],

    'response' => [
        'cart-not-found' => 'Cart not found or invalid.',
        'invalid-transaction' => 'Invalid transaction. Please try again.',
        'order-creation-failed' => 'The payment succeeded but the order could not be created. Please contact support.',
        'payment-cancelled' => 'The payment was cancelled.',
        'payment-failed' => 'The payment failed. Please try again.',
        'payment-pending' => 'Your payment is still pending. Please wait a moment and try again.',
        'payment-success' => 'The payment completed successfully.',
        'provide-credentials' => 'Please provide valid Papi credentials in the admin panel.',
        'supported-currency-error' => 'The currency :currency is not supported. Supported Currencies: :supportedCurrencies.',
        'verification-failed' => 'The payment could not be verified.',
    ],
];
