<?php

namespace Webkul\AirtelMoney\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Payment\Payment\Payment;

class AirtelMoneyPayment extends Payment
{
    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'airtel_money';

    /**
     * Airtel Money sandbox API URL.
     */
    const SANDBOX_URL = 'https://openapiuat.airtel.mg';

    /**
     * Airtel Money production API URL.
     */
    const PRODUCTION_URL = 'https://openapi.airtel.mg';

    /**
     * Airtel Money transaction status values.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IN_PROGRESS = 'tip';

    public const STATUS_EXPIRED = 'te';

    public const STATUS_AMBIGUOUS = 'ta';

    /**
     * Currencies Airtel Money settles in.
     *
     * @var string[]
     */
    protected $supportedCurrencies = ['MGA'];

    /**
     * Get redirect URL for Airtel Money payment.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('airtel_money.redirect');
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
        return $this->getConfigData('title') ?? trans('airtel_money::app.title');
    }

    /**
     * Get payment method description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description') ?? trans('airtel_money::app.description');
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
     * Get Airtel Money base URL for the configured environment.
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
     * Get Airtel Money client ID.
     *
     * @return string|null
     */
    public function getClientId()
    {
        return $this->getConfigData('client_id');
    }

    /**
     * Get Airtel Money client secret.
     *
     * @return string|null
     */
    public function getClientSecret()
    {
        return $this->getConfigData('client_secret');
    }

    /**
     * Get Airtel Money merchant ID.
     *
     * @return string|null
     */
    public function getMerchantId()
    {
        return $this->getConfigData('merchant_id');
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
            && ! empty($this->getMerchantId())
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
     * Generate an OAuth2 bearer token for Airtel Money API.
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        return Cache::remember('airtel_money_access_token_'.($this->isSandbox() ? 'sandbox' : 'production'), 3500, function () {
            $response = Http::connectTimeout(5)->timeout(20)->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->getBaseUrl().'/auth/oauth2/token', [
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'grant_type' => 'client_credentials',
            ]);

            if ($response->failed()) {
                report(new \Exception('Airtel Money token request failed with HTTP status '.$response->status().'.'));

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Get common headers for Airtel Money API requests.
     *
     * @return array
     */
    public function getHeaders()
    {
        return [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'X-Country' => 'MG',
            'X-Currency' => 'MGA',
            'Content-Type' => 'application/json',
            'Accept' => '*/*',
        ];
    }

    /**
     * Generate a unique merchant transaction ID.
     *
     * @param  int  $cartId
     * @return string
     */
    public function generateMerchantTransactionId($cartId)
    {
        return 'AIRTEL_'.Str::upper(Str::random(8)).'_'.$cartId;
    }

    /**
     * Initiate a USSD push payment via Airtel Money.
     *
     * @param  Cart  $cart
     * @param  string  $customerPhone  The customer's Airtel Money phone number.
     * @return array|null
     */
    public function initiatePayment($cart, $customerPhone)
    {
        $reference = $this->generateMerchantTransactionId($cart->id);

        $response = Http::connectTimeout(5)->timeout(20)->withHeaders($this->getHeaders())
            ->post($this->getBaseUrl().'/merchant/v1/payments/', [
                'reference' => $reference,
                'subscriber' => [
                    'country' => 'MG',
                    'currency' => 'MGA',
                    'msisdn' => $this->normalizeMsisdn($customerPhone),
                ],
                'transaction' => [
                    'amount' => (int) round($cart->base_grand_total),
                    'country' => 'MG',
                    'currency' => 'MGA',
                    'id' => $reference,
                ],
            ]);

        if ($response->failed()) {
            report(new \RuntimeException('Airtel Money payment initiation failed with HTTP status '.$response->status().'.'));

            return null;
        }

        $data = $response->json();

        $data['reference'] = $reference;
        $data['customer_phone'] = $customerPhone;

        return $data;
    }

    /**
     * Check the status of an Airtel Money transaction.
     *
     * @param  string  $transactionId
     * @return array|null
     */
    public function getTransactionStatus($transactionId)
    {
        $response = Http::connectTimeout(5)->timeout(20)->withHeaders($this->getHeaders())
            ->get($this->getBaseUrl().'/standard/v1/payments/'.$transactionId);

        if ($response->failed()) {
            report(new \RuntimeException('Airtel Money status request failed with HTTP status '.$response->status().'.'));

            return null;
        }

        return $response->json();
    }

    /**
     * Resolve the final payment status from Airtel Money response data.
     *
     * @param  array  $response
     * @return string|null
     */
    public function resolveTransactionStatus($response)
    {
        $rawStatus = strtoupper($response['data']['transaction']['status'] ?? $response['status'] ?? '');

        $map = [
            'TS' => self::STATUS_COMPLETED,
            'SUCCESS' => self::STATUS_COMPLETED,
            'COMPLETED' => self::STATUS_COMPLETED,
            'TF' => self::STATUS_FAILED,
            'FAILED' => self::STATUS_FAILED,
            'TIP' => self::STATUS_IN_PROGRESS,
            'TA' => self::STATUS_AMBIGUOUS,
            'TE' => self::STATUS_EXPIRED,
            'PENDING' => self::STATUS_PENDING,
        ];

        return $map[$rawStatus] ?? null;
    }

    /**
     * Verify the Airtel Money webhook signature.
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
     * Normalize a phone number to MSISDN format without country code.
     *
     * @param  string  $phone
     * @return string
     */
    public function normalizeMsisdn($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $phone);

        if (str_starts_with($phone, '261')) {
            $phone = substr($phone, 3);
        }

        if (str_starts_with($phone, '+261')) {
            $phone = substr($phone, 4);
        }

        return ltrim($phone, '0');
    }
}
