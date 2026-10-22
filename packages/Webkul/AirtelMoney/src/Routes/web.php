<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Webkul\AirtelMoney\Http\Controllers\AirtelMoneyController;

Route::controller(AirtelMoneyController::class)
    ->middleware('web')
    ->prefix('airtel-money')
    ->group(function () {
        Route::get('redirect', 'redirect')->name('airtel_money.redirect');

        Route::post('pay', 'pay')
            ->middleware('throttle:8,1')
            ->name('airtel_money.pay');

        Route::post('callback', 'callback')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->name('airtel_money.callback');

        Route::get('cancel', 'cancel')->name('airtel_money.cancel');

        Route::post('webhook', 'webhook')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->name('airtel_money.webhook');
    });
