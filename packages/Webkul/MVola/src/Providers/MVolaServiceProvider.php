<?php

namespace Webkul\MVola\Providers;

use Illuminate\Support\ServiceProvider;

class MVolaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'mvola');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'mvola');
    }

    /**
     * Merge the MVola configuration with the application.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/payment-methods.php', 'payment_methods'
        );
    }
}
