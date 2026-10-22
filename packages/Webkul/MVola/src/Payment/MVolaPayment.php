<?php

namespace Webkul\MVola\Payment;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Payment\Payment\Payment;

class MVolaPayment extends Payment
{
    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'mvola';

    /**
     * MVola sandbox API URL.
     */
    const SANDBOX_URL = 'https://devapi.mvola.mg';

    /**
     * MVola production API URL.
     */
    const PRODUCTION_URL = 'https://api.mvola.mg';

    /**
     * MVola transaction status values.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * Currencies MVola settles in.
     *
     * @var string[]
     */
    protected $supportedCurrencies = ['MGA'];

    /**
     * Get redirect URL for MVola payment.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('mvola.redirect');
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
        return $this->getConfigData('title') ?? trans('mvola::app.title');
    }

    /**
     * Get payment method description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description') ?? trans('mvola::app.description');
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
     * Get MVola base URL for the configured environment.
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
     * Get MVola consumer key.
     *
     * @return string|null
     */
    public function getConsumerKey()
    {
        return $this->getConfigData('consumer_key');
    }

    /**
     * Get MVola consumer secret.
     *
     * @return string|null
     */
    public function getConsumerSecret()
    {
        return $this->getConfigData('consumer_secret');
    }

    /**
     * Get MVola merchant number.
     *
     * @return string|null
     */
    public function getMerchantNumber()
    {
        return $this->getConfigData('merchant_number');
    }

    /**
     * Get MVola partner name.
     *
     * @return string|null
     */
    public function getPartnerName()
    {
        return $this->getConfigData('partner_name');
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
        return ! empty($this->getConsumerKey())
            && ! empty($this->getConsumerSecret())
            && ! empty($this->getMerchantNumber())
            && ! empty($this->getPartnerName())
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
     * Generate an OAuth2 access token for MVola API.
     *
     * @return string|null
     */
    public function getAccessToken()
    {
        return Cache::remember('mvola_access_token_'.($this->isSandbox() ? 'sandbox' : 'production'), 3500, function () {
            $response = Http::connectTimeout(5)->timeout(20)->asForm()->withHeaders([
                'Authorization' => 'Basic '.base64_encode($this->getConsumerKey().':'.$this->getConsumerSecret()),
                'Cache-Control' => 'no-cache',
            ])->post($this->getBaseUrl().'/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'EXT_INT_MVOLA_SCOPE',
            ]);

            if ($response->failed()) {
                report(new \Exception('MVola token request failed with HTTP status '.$response->status().'.'));

                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Get common headers for MVola API requests.
     *
     * @param  string  $correlationId
     * @return array
     */
    public function getHeaders($correlationId = null)
    {
        $correlationId = $correlationId ?: Str::uuid()->toString();

        return [
            'Authorization' => 'Bearer '.$this->getAccessToken(),
            'version' => '1.0',
            'X-CorrelationID' => $correlationId,
            'UserLanguage' => 'MG',
            'UserAccountIdentifier' => 'msisdn;'.$this->getMerchantNumber(),
            'partnerName' => $this->getPartnerName(),
            'Content-Type' => 'application/json',
            'Accept-Charset' => 'utf-8',
        ];
    }

    /**
     * Initiate a merchant payment via MVola.
     *
     * @param  Cart  $cart
     * @param  string  $customerPhone  The customer's MVola phone number.
     * @return array|null
     */
    public function initiatePayment($cart, $customerPhone)
    {
        $correlationId = Str::uuid()->toString();

        $response = Http::connectTimeout(5)->timeout(20)->withHeaders($this->getHeaders($correlationId))
            ->withHeaders(['X-Callback-URL' => route('mvola.webhook')])
            ->post($this->getBaseUrl().'/mvola/mm/transactions/type/merchantpay/1.0.0/', [
                'amount' => (string) round($cart->base_grand_total),
                'currency' => 'Ar',
                'descriptionText' => trans('mvola::app.payment-description', ['cart_id' => $cart->id]),
                'requestDate' => now()->toIso8601String(),
                'requestingOrganisationTransactionReference' => $correlationId,
                'debitParty' => [['key' => 'msisdn', 'value' => $this->normalizeMsisdn($customerPhone)]],
                'creditParty' => [['key' => 'msisdn', 'value' => $this->getMerchantNumber()]],
                'metadata' => [
                    ['key' => 'partnerName', 'value' => $this->getPartnerName()],
                    ['key' => 'cart_id', 'value' => (string) $cart->id],
                ],
            ]);

        if ($response->failed()) {
            report(new \RuntimeException('MVola payment initiation failed with HTTP status '.$response->status().'.'));

            return null;
        }

        $data = $response->json();

        $data['correlation_id'] = $correlationId;
        $data['customer_phone'] = $customerPhone;

        return $data;
    }

    /**
     * Get the status of an MVola transaction.
     *
     * @param  string  $correlationId
     * @return array|null
     */
    public function getTransactionStatus($correlationId)
    {
        $response = Http::connectTimeout(5)->timeout(20)->withHeaders($this->getHeaders($correlationId))
            ->get($this->getBaseUrl().'/mvola/mm/transactions/type/merchantpay/1.0.0/status/'.$correlationId);

        if ($response->failed()) {
            report(new \RuntimeException('MVola status request failed with HTTP status '.$response->status().'.'));

            return null;
        }

        return $response->json();
    }

    /**
     * Verify the MVola webhook signature.
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
