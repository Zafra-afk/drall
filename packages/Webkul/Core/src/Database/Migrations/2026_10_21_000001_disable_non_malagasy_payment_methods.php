<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable payment methods not approved for the Drall storefront.
     */
    public function up(): void
    {
        Schema::create('drall_payment_method_config_backup', function (Blueprint $table) {
            $table->unsignedInteger('config_id')->primary();
            $table->text('value');
        });

        $codes = [
            'sales.payment_methods.cashondelivery.active',
            'sales.payment_methods.moneytransfer.active',
            'sales.payment_methods.paypal_smart_button.active',
            'sales.payment_methods.paypal_standard.active',
            'sales.payment_methods.stripe.active',
            'sales.payment_methods.razorpay.active',
            'sales.payment_methods.phonepe.active',
            'sales.payment_methods.payu.active',
            'sales.payment_methods.payglocal.active',
            'sales.payment_methods.mvola.active',
            'sales.payment_methods.orange_money.active',
            'sales.payment_methods.airtel_money.active',
            'sales.payment_methods.malagasy_card.active',
        ];

        $activeConfigs = DB::table('core_config')
            ->whereIn('code', $codes)
            ->get(['id', 'value']);

        foreach ($activeConfigs as $config) {
            DB::table('drall_payment_method_config_backup')->insert([
                'config_id' => $config->id,
                'value' => $config->value,
            ]);
        }

        DB::table('core_config')
            ->whereIn('code', $codes)
            ->update([
                'value' => '0',
                'updated_at' => now(),
            ]);
    }

    /**
     * Restore the prior payment-method settings.
     */
    public function down(): void
    {
        $configs = DB::table('drall_payment_method_config_backup')->get();

        foreach ($configs as $config) {
            DB::table('core_config')
                ->where('id', $config->config_id)
                ->update([
                    'value' => $config->value,
                    'updated_at' => now(),
                ]);
        }

        Schema::dropIfExists('drall_payment_method_config_backup');
    }
};
