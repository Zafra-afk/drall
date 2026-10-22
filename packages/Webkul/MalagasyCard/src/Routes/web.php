<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Webkul\MalagasyCard\Http\Controllers\MalagasyCardController;

Route::controller(MalagasyCardController::class)
    ->middleware('web')
    ->prefix('malagasy-card')
    ->group(function () {
        Route::get('redirect', 'redirect')->name('malagasy_card.redirect');

        Route::get('callback', 'callback')->name('malagasy_card.callback');

        Route::get('cancel', 'cancel')->name('malagasy_card.cancel');

        Route::post('webhook', 'webhook')
            ->withoutMiddleware(PreventRequestForgery::class)
            ->name('malagasy_card.webhook');
    });
