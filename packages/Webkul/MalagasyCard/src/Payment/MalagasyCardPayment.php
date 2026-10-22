<?php

namespace Webkul\MalagasyCard\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Checkout\Contracts\Cart;
use Webkul\Payment\Payment\Payment;

class MalagasyCardPayment extends Payment
{
    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'malagasy_card';

    /**
     * Papi API base URL.
     *
     * @var string
     */
    const API_URL = 'https://app.papi.mg/engine/api';

    /**
     * Webhook replay protection window, in seconds.
     *
     * @var int
     */
    public const WEBHOOK_TOLERANCE = 300;

    /**
     * Papi payment outcome values reported on notifications and read-back.
     */
    public const STATUS_SUCCESSFUL = 'SUCCESS';

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_FAILED = 'FAILED';

    /**
     * Currencies Papi supports for card payments.
     *
     * Papi is purpose-built for Madagascar and processes natively in MGA.
     *
     * @var string[]
     */
    protected $supportedCurrencies = ['MGA'];

    /**
     * Get redirect URL for Malagasy Card payment.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('malagasy_card.redirect');
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
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigData('title') ?? trans('malagasy_card::app.title');
    }

    /**
     * Get payment method description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description') ?? trans('malagasy_card::app.description');
    }

    /**
     * Get payment method image.
     *
     * @return string
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : bagisto_asset('images/papi.png', 'shop');
    }

    /**
     * Check if sandbox (test) mode is enabled.
     *
     * @return bool
     */
    public function isSandbox()
    {
        return (bool) $this->getConfigData('sandbox');
    }

    /**
     * Get the Papi API key for the configured environment.
     *
     * @return string|null
     */
    public function getApiKey()
    {
        return $this->getConfigData('api_key');
    }

    /**
     * Get the Papi merchant identifier.
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
        return ! empty($this->getApiKey());
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
     * Get common headers for Papi API requests.
     *
     * @return array
     */
    public function getHeaders()
    {
        return [
            'Token' => $this->getApiKey(),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Generate a unique transaction reference.
     *
     * @param  int  $cartId
     * @return string
     */
    public function generateTxRef($cartId)
    {
        return 'MC_'.Str::upper(Str::random(10)).'_'.$cartId;
    }

    /**
     * Create a Papi payment link and return the hosted payment details.
     *
     * @param  Cart  $cart
     * @return array|null
     */
    public function createPaymentLink($cart)
    {
        $reference = $this->generateTxRef($cart->id);

        $currency = $this->resolveCurrency($cart);

        if ($currency === null) {
            return null;
        }

        $clientName = trim(($cart->billing_address->first_name ?? '').' '.($cart->billing_address->last_name ?? ''));

        if ($clientName === '') {
            $clientName = 'Customer';
        }

        $body = [
            'reference' => $reference,
            'amount' => (float) $cart->base_grand_total,
            'currency' => $currency,
            'description' => trans('malagasy_card::app.description'),
            'clientName' => $clientName,
            'successUrl' => route('malagasy_card.callback', ['reference' => $reference]),
            'failureUrl' => route('malagasy_card.cancel'),
            'notificationUrl' => route('malagasy_card.webhook'),
            'isTestMode' => $this->isSandbox(),
        ];

        $payerEmail = $cart->billing_address->email ?? $cart->customer_email ?? null;

        if (! empty($payerEmail)) {
            $body['payerEmail'] = $payerEmail;
        }

        $payerPhone = $cart->billing_address->phone ?? null;

        if (! empty($payerPhone)) {
            $body['payerPhone'] = $payerPhone;
        }

        $response = Http::withHeaders($this->getHeaders())
            ->post(self::API_URL.'/payment-links', $body);

        if ($response->failed()) {
            report(new \Exception('Papi payment link creation failed with HTTP status '.$response->status().'.'));

            return null;
        }

        $data = $response->json();

        $link = $data['data']['paymentLink'] ?? $data['paymentLink'] ?? null;

        if (empty($link)) {
            return null;
        }

        $data['reference'] = $reference;
        $data['currency'] = $currency;
        $data['link'] = $link;

        return $data;
    }

    /**
     * Resolve the currency to use for the payment.
     *
     * Papi processes natively in MGA.
     *
     * @param  Cart  $cart
     */
    public function resolveCurrency($cart): ?string
    {
        $currency = strtoupper($cart->base_currency_code ?? core()->getBaseCurrencyCode());

        if ($this->isCurrencySupported($currency)) {
            return $currency;
        }

        return null;
    }

    /**
     * Fetch a Papi payment link by its reference using the read-back API.
     *
     * @param  string  $reference
     * @return array|null
     */
    public function fetchPayment($reference)
    {
        $response = Http::withHeaders($this->getHeaders())
            ->get(self::API_URL.'/payment-links/'.$reference);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Verify the Papi webhook signature using HMAC-SHA256.
     *
     * Papi sends the X-Papi-Signature header in the format
     * `t=<timestamp>,v1=<signature>`, where v1 is the lowercase
     * hexadecimal HMAC-SHA256 of `{timestamp}.{raw_body}` keyed by
     * the webhook signing secret. A timestamp older than 300 seconds
     * is rejected to prevent replays.
     *
     * @return bool
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature)
    {
        $secret = $this->getWebhookSecret();

        if (empty($secret) || empty($signature)) {
            return false;
        }

        $parts = $this->parsePapiSignature($signature);

        if ($parts === null) {
            return false;
        }

        [$timestamp, $expectedSignature] = $parts;

        if (abs(time() - (int) $timestamp) > self::WEBHOOK_TOLERANCE) {
            return false;
        }

        $computed = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return hash_equals($expectedSignature, $computed);
    }

    /**
     * Parse the Papi X-Papi-Signature header into its timestamp and HMAC.
     */
    protected function parsePapiSignature(string $signature): ?array
    {
        $parts = [];

        foreach (explode(',', $signature) as $item) {
            $pair = explode('=', trim($item), 2);

            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        $timestamp = $parts['t'] ?? '';
        $hmacSignature = $parts['v1'] ?? '';

        if (! ctype_digit($timestamp) || ! preg_match('/\A[0-9a-f]{64}\z/', $hmacSignature)) {
            return null;
        }

        return [(int) $timestamp, $hmacSignature];
    }
}
