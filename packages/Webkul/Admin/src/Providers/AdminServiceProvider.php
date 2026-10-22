<?php

namespace Webkul\Admin\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Admin\CommandPalette\CommandPalette;
use Webkul\Admin\CommandPalette\Providers\ActionProvider;
use Webkul\Admin\CommandPalette\Providers\ConfigurationProvider;
use Webkul\Admin\CommandPalette\Providers\NavigationProvider;
use Webkul\Admin\Http\Middleware\Locale;
use Webkul\Core\Http\Middleware\PreventRequestsDuringMaintenance;

class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerConfig();

        $this->registerCommandPalette();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Route::middleware(['web', Locale::class, PreventRequestsDuringMaintenance::class])->group(__DIR__.'/../Routes/web.php');

        $this->registerBroadcasting();

        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'admin');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'admin');

        Blade::anonymousComponentPath(__DIR__.'/../Resources/views/components', 'admin');

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Register the broadcast authorization route and the channels the panel listens on.
     */
    protected function registerBroadcasting(): void
    {
        Broadcast::routes();

        require __DIR__.'/../Routes/channels.php';
    }

    /**
     * Register package config.
     */
    protected function registerConfig(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/menu.php',
            'menu.admin'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/acl.php',
            'acl'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/system.php',
            'core'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__).'/Config/command-palette.php',
            'command_palette'
        );

        $allowedPaymentMethods = config('app.allowed_payment_methods', []);

        $coreConfig = collect(config('core', []))
            ->reject(function (array $item) use ($allowedPaymentMethods) {
                $key = $item['key'] ?? '';

                return str_starts_with($key, 'sales.payment_methods.')
                    && ! in_array(substr($key, strlen('sales.payment_methods.')), $allowedPaymentMethods, true);
            })
            ->values()
            ->all();

        config([
            'core' => array_merge(
                $coreConfig,
                require dirname(__DIR__).'/Config/malagasy-payment-methods.php'
            ),
        ]);
    }

    /**
     * Register what the command palette searches over.
     */
    protected function registerCommandPalette(): void
    {
        $this->app->singleton(CommandPalette::class);

        $this->callAfterResolving(CommandPalette::class, function (CommandPalette $palette) {
            $palette->register(NavigationProvider::class);
            $palette->register(ConfigurationProvider::class);
            $palette->register(ActionProvider::class);
        });
    }
}
