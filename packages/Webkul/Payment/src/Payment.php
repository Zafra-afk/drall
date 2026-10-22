<?php

namespace Webkul\Payment;

use Illuminate\Support\Facades\Config;
use Webkul\Checkout\Contracts\Cart;

class Payment
{
    /**
     * Returns all supported payment methods
     *
     * @return array
     */
    public function getSupportedPaymentMethods()
    {
        return [
            'payment_methods' => $this->getPaymentMethods(),
        ];
    }

    /**
     * Returns all supported payment methods
     *
     * @return array
     */
    public function getPaymentMethods()
    {
        $paymentMethods = [];
        $allowedMethods = Config::get('app.allowed_payment_methods', []);

        foreach (Config::get('payment_methods', []) as $code => $paymentMethodConfig) {
            if (! in_array($code, $allowedMethods, true)) {
                continue;
            }

            $paymentMethod = app($paymentMethodConfig['class']);

            if (
                ! (bool) Config::get('payment_methods.'.$code.'.active')
                && ! (bool) $paymentMethod->getConfigData('active')
            ) {
                continue;
            }

            $paymentMethods[] = [
                'method' => $paymentMethod->getCode(),
                'method_title' => $paymentMethod->getTitle(),
                'description' => $paymentMethod->getDescription(),
                'sort' => $paymentMethod->getSortOrder(),
                'image' => $paymentMethod->getImage(),
                'available' => $paymentMethod->isAvailable(),
            ];
        }

        usort($paymentMethods, function ($a, $b) {
            if ($a['sort'] == $b['sort']) {
                return 0;
            }

            return ($a['sort'] < $b['sort']) ? -1 : 1;
        });

        return $paymentMethods;
    }

    /**
     * Check whether a storefront payment method is allowed and available.
     */
    public function isPaymentMethodAvailable(?string $code): bool
    {
        if (
            ! $code
            || ! in_array($code, Config::get('app.allowed_payment_methods', []), true)
        ) {
            return false;
        }

        $paymentMethodConfig = Config::get('payment_methods.'.$code);

        if (! $paymentMethodConfig || empty($paymentMethodConfig['class'])) {
            return false;
        }

        return app($paymentMethodConfig['class'])->isAvailable();
    }

    /**
     * Returns payment redirect url if have any
     *
     * @param  Cart  $cart
     * @return string
     */
    public function getRedirectUrl($cart)
    {
        $payment = app(Config::get('payment_methods.'.$cart->payment->method.'.class'));

        return $payment->getRedirectUrl();
    }

    /**
     * Returns payment method additional information
     *
     * @param  string  $code
     * @return array
     */
    public static function getAdditionalDetails($code)
    {
        $paymentMethodClass = app(Config::get('payment_methods.'.$code.'.class'));

        return $paymentMethodClass->getAdditionalDetails();
    }
}
