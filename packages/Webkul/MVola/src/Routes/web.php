<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Webkul\MVola\Http\Controllers\MVolaController;

Route::controller(MVolaController::class)
    ->middleware('web')
    ->prefix('mvola')
    ->group(function () {
        Route::get('redirect', 'redirect')->name('mvola.redirect');

        Route::post('pay', 'pay')
            ->middleware('throttle:8,1')
            ->name('mvola.pay');

        Route::post('callback', 'callback')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->name('mvola.callback');

        Route::get('cancel', 'cancel')->name('mvola.cancel');

        Route::post('webhook', 'webhook')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->name('mvola.webhook');
    });
