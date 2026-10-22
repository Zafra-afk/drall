<?php

use Webkul\Payment\Payment\CashOnDelivery;
use Webkul\Payment\Payment\MoneyTransfer;

return [
    'cashondelivery' => [
        'class' => CashOnDelivery::class,
        'code' => 'cashondelivery',
        'title' => 'Cash On Delivery',
        'description' => 'Cash On Delivery',
        'active' => false,
        'generate_invoice' => false,
        'sort' => 7,
    ],

    'moneytransfer' => [
        'class' => MoneyTransfer::class,
        'code' => 'moneytransfer',
        'title' => 'Bank Transfer',
        'description' => 'Bank Transfer',
        'active' => false,
        'generate_invoice' => false,
        'sort' => 8,
    ],
];
