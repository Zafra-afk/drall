<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Webkul\OrangeMoney\Http\Controllers\OrangeMoneyController;

Route::controller(OrangeMoneyController::class)
    ->middleware('web')
    ->prefix('orange-money')
    ->group(function () {
        Route::get('redirect', 'redirect')->name('orange_money.redirect');

        Route::post('pay', 'pay')
            ->middleware('throttle:8,1')
            ->name('orange_money.pay');

        Route::post('callback', 'callback')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->name('orange_money.callback');

        Route::get('cancel', 'cancel')->name('orange_money.cancel');

        Route::post('webhook', 'webhook')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->name('orange_money.webhook');
    });
