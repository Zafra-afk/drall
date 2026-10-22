<?php

namespace Webkul\OrangeMoney\Providers;

use Illuminate\Support\ServiceProvider;

class OrangeMoneyServiceProvider extends ServiceProvider
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

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'orange_money');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'orange_money');
    }

    /**
     * Merge the Orange Money configuration with the application.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/payment-methods.php', 'payment_methods'
        );
    }
}
