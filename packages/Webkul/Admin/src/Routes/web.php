<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Inventory\ProcurementController;
use Webkul\Core\Http\Middleware\NoCacheMiddleware;

/**
 * Auth routes.
 */
require 'web/auth-routes.php';

Route::group(['middleware' => ['admin', NoCacheMiddleware::class], 'prefix' => config('app.admin_url')], function () {
    Route::controller(ProcurementController::class)->prefix('inventory/procurement')->group(function () {
        Route::get('', 'index')->name('admin.inventory.procurement.index');
        Route::get('products/search', 'searchProducts')->name('admin.inventory.procurement.products.search');
        Route::post('suppliers', 'storeSupplier')->name('admin.inventory.procurement.suppliers.store');
        Route::put('products/{id}', 'storeProductDetails')->name('admin.inventory.procurement.products.update');
        Route::post('orders', 'storeOrder')->name('admin.inventory.procurement.orders.store');
        Route::post('orders/{id}/place', 'place')->name('admin.inventory.procurement.orders.place');
        Route::post('orders/{id}/receive', 'receive')->name('admin.inventory.procurement.orders.receive');
        Route::post('orders/{id}/cancel', 'cancel')->name('admin.inventory.procurement.orders.cancel');
        Route::post('adjustments', 'adjust')->name('admin.inventory.procurement.adjustments.store');
    });

    /**
     * Sales routes.
     */
    require 'web/sales-routes.php';

    /**
     * Catalog routes.
     */
    require 'web/catalog-routes.php';

    /**
     * Customers routes.
     */
    require 'web/customers-routes.php';

    /**
     * Marketing routes.
     */
    require 'web/marketing-routes.php';

    /**
     * CMS routes.
     */
    require 'web/cms-routes.php';

    /**
     * Reporting routes.
     */
    require 'web/reporting-routes.php';

    /**
     * Appearance routes.
     */
    require 'web/appearance-routes.php';

    /**
     * Settings routes.
     */
    require 'web/settings-routes.php';

    /**
     * Configuration routes.
     */
    require 'web/configuration-routes.php';

    /**
     * Notification routes.
     */
    require 'web/notification-routes.php';

    /**
     * Help & Resources routes.
     */
    require 'web/help-routes.php';

    /**
     * Remaining routes.
     */
    require 'web/rest-routes.php';
});
