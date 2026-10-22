<?php

namespace Webkul\OrangeMoney\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Payment\Payment\Payment;

class OrangeMoneyPayment extends Payment
{
    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'orange_money';

    /**
     * Orange Money sandbox API URL.
     */
    const SANDBOX_URL = 'https://api.sandbox.orange.com';

    /**
     * Orange Money production API URL.
     */
    const PRODUCTION_URL = 'https://api.orange.com';

    /**
     * Orange Money transaction status values.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * Currencies Orange Money settles in.
     *
     * @var string[]
     */
    protected $supportedCurrencies = ['MGA'];

    /**
     * Get redirect URL for Orange Money payment.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('orange_money.redirect');
    }

    /**
     * Check if payment method is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return parent::isAvailable()
            && $this->hasValidCredentials()
            && $this->isCurrencySupported(core()->getBaseCurrencyCode());
    }

    /**
     * Get payment method title.
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->getConfigData('title') ?? trans('orange_money::app.title');
    }

    /**
     * Get payment method description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description') ?? trans('orange_money::app.description');
    }

    /**
     * Get payment method image.
     *
     * @return string
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : null;
    }

    /**
     * Check if sandbox mode is enabled.
     *
     * @return bool
     */
    public function isSandbox()
    {
        return (bool) $this->getConfigData('sandbox');
    }

    /**
     * Get Orange Money base URL for the configured environment.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->isSandbox()
            ? self::SANDBOX_URL
            : self::PRODUCTION_URL;
    }

    /**
     * Get Orange Money client ID.
     *
     * @return string|null
     */
    public function getClientId()
    {
        return $this->getConfigData('client_id');
    }

    /**
     * Get Orange Money client secret.
     *
     * @return string|null
     */
    public function getClientSecret()
    {
        return $this->getConfigData('client_secret');
    }

    /**
     * Get Orange Money merchant key.
     *
     * @return string|null
     */
    public function getMerchantKey()
    {
        return $this->getConfigData('merchant_key');
    }

    /**
     * Get supported currencies.
     *
     * @return array
     */
    public function getSupportedCurrencies()
    {
        return $this->supportedCurrencies;
    }

    /**
     * Check if currency is supported.
     *
     * @param  string  $currency
     * @return bool
     */
    public function isCurrencySupported($currency)
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies);
    }

    /**
     * Check if required credentials are configured.
     *
     * @return bool
     */
    public function hasValidCredentials()
    {
        return ! empty($this->getClientId())
            && ! empty($this->getClientSecret())
            && ! empty($this->getMerchantKey())
            && ! empty($this->getWebhookSecret());
    }

    /**
     * Get the signing secret of the webhook endpoint.
     *
     * @return string|null
     */
    public function getWebhookSecret()
    {
        return $this->getConfigData('webhook_secret');
    }

    /**
     * Generate an OAuth2 access token for Orange Money API.
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        return Cache::remember('orange_money_access_token_'.($this->isSandbox() ? 'sandbox' : 'production'), 3500, function () {
            $response = Http::connectTimeout(5)->timeout(20)->asForm()->post($this->getBaseUrl().'/oauth/v2/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
            ]);

            if ($response->failed()) {
                report(new \Exception('Orange Money token request failed with HTTP status '.$response->status().'.'));

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Get common headers for Orange Money API requests.
     *
     * @return array
     */
    public function getHeaders()
    {
        return [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Initiate a payment via Orange Money Web Payment API.
     *
     * @param  Cart  $cart
     * @param  string  $customerPhone  The customer's phone number.
     * @return array|null
     */
    public function initiatePayment($cart, $customerPhone)
    {
        $orderId = 'ORANGE_'.$cart->id.'_'.Str::upper(Str::random(10));

        $response = Http::connectTimeout(5)->timeout(20)->withHeaders($this->getHeaders())
            ->post($this->getBaseUrl().'/paymentWebV2/merchants/'.$this->getMerchantKey().'/payment', [
                'order' => [
                    'orderId' => $orderId,
                    'amount' => (int) round($cart->base_grand_total),
                    'currency' => 'MGA',
                    'returnUrl' => route('orange_money.callback'),
                ],
                'customer' => [
                    'phone' => $this->normalizePhone($customerPhone),
                ],
            ]);

        if ($response->failed()) {
            report(new \RuntimeException('Orange Money payment initiation failed with HTTP status '.$response->status().'.'));

            return null;
        }

        $data = $response->json();

        $data['order_id'] = $orderId;
        $data['customer_phone'] = $customerPhone;

        return $data;
    }

    /**
     * Get payment status from Orange Money.
     *
     * @param  string  $orderId
     * @return array|null
     */
    public function getPaymentStatus($orderId)
    {
        $response = Http::connectTimeout(5)->timeout(20)->withHeaders($this->getHeaders())
            ->get($this->getBaseUrl().'/paymentWebV2/merchants/'.$this->getMerchantKey().'/payment/'.$orderId);

        if ($response->failed()) {
            report(new \RuntimeException('Orange Money status request failed with HTTP status '.$response->status().'.'));

            return null;
        }

        return $response->json();
    }

    /**
     * Verify the Orange Money webhook signature.
     *
     * @param  array  $payload
     * @param  string  $signature
     * @return bool
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        $secret = $this->getWebhookSecret();

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $payloadJson = is_array($payload) ? json_encode($payload) : $payload;

        $expectedSignature = hash_hmac('sha256', $payloadJson, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Normalize a phone number to the format expected by Orange Money.
     *
     * @param  string  $phone
     * @return string
     */
    public function normalizePhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);

        if (str_starts_with($phone, '261')) {
            $phone = substr($phone, 3);
        }

        return ltrim($phone, '0');
    }
}
