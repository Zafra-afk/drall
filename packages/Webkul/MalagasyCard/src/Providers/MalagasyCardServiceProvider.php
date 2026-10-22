<?php

namespace Webkul\MalagasyCard\Providers;

use Illuminate\Support\ServiceProvider;

class MalagasyCardServiceProvider extends ServiceProvider
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

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'malagasy_card');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'malagasy_card');
    }

    /**
     * Merge the Malagasy Card configuration with the application.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/payment-methods.php', 'payment_methods'
        );
    }
}
