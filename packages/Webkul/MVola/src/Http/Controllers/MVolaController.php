<?php

namespace Webkul\MVola\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\MVola\Payment\MVolaPayment;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

class MVolaController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CartRepository $cartRepository,
        protected OrderRepository $orderRepository,
        protected OrderTransactionRepository $orderTransactionRepository,
        protected InvoiceRepository $invoiceRepository,
        protected MVolaPayment $mVolaPayment,
    ) {}

    /**
     * Redirect to the MVola payment page.
     */
    public function redirect(): RedirectResponse|View
    {
        if (! $this->mVolaPayment->isAvailable()) {
            session()->flash('error', trans('mvola::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $cart = Cart::getCart();

        if (! $cart) {
            session()->flash('error', trans('mvola::app.response.cart-not-found'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $currency = strtoupper($cart->base_currency_code ?? core()->getBaseCurrencyCode());

        if (! $this->mVolaPayment->isCurrencySupported($currency)) {
            session()->flash('error', trans('mvola::app.response.supported-currency-error', [
                'currency' => $currency,
                'supportedCurrencies' => implode(', ', $this->mVolaPayment->getSupportedCurrencies()),
            ]));

            return redirect()->route('shop.checkout.cart.index');
        }

        return view('mvola::checkout.redirect', [
            'amount' => $cart->base_grand_total,
            'currency' => $currency,
            'customerPhone' => $cart->billing_address?->phone ?? '',
            'paymentStarted' => $this->hasRecentPaymentAttempt('mvola', $cart->id),
            'transactionReference' => session('mvola.payment.reference'),
        ]);
    }

    /**
     * Initiate an MVola payment for the active cart.
     */
    public function pay(): RedirectResponse
    {
        $validated = request()->validate([
            'customer_phone' => ['required', 'string', 'regex:/^(?:\\+?261|0)?3[23478][0-9]{7}$/'],
        ]);

        $cart = Cart::getCart();

        if (
            ! $this->mVolaPayment->isAvailable()
            || ! $cart
            || $cart->payment?->method !== 'mvola'
        ) {
            session()->flash('error', trans('mvola::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        if ($this->hasRecentPaymentAttempt('mvola', $cart->id)) {
            session()->flash('info', trans('mvola::app.response.payment-pending'));

            return redirect()->route('mvola.redirect');
        }

        try {
            $payment = $this->mVolaPayment->initiatePayment($cart, $validated['customer_phone']);
        } catch (ConnectionException $exception) {
            report($exception);

            session()->flash('error', trans('mvola::app.response.payment-failed'));

            return redirect()->route('mvola.redirect');
        }

        if (! $payment || empty($payment['correlation_id'])) {
            session()->flash('error', trans('mvola::app.response.payment-failed'));

            return redirect()->route('mvola.redirect');
        }

        session()->put('mvola.payment', [
            'cart_id' => $cart->id,
            'reference' => $payment['correlation_id'],
            'created_at' => now()->timestamp,
        ]);

        session()->flash('info', trans('mvola::app.response.payment-pending'));

        return redirect()->route('mvola.redirect');
    }

    /**
     * Check whether the current cart has an unexpired payment attempt.
     */
    protected function hasRecentPaymentAttempt(string $method, int $cartId): bool
    {
        $attempt = session($method.'.payment', []);

        return (int) ($attempt['cart_id'] ?? 0) === $cartId
            && (int) ($attempt['created_at'] ?? 0) > now()->subMinutes(15)->timestamp;
    }

    /**
     * Handle MVola payment callback.
     *
     * MVola sends a POST notification with transaction details after the customer
     * completes or cancels the payment on their device.
     */
    public function callback(): RedirectResponse
    {
        $signature = request()->header('X-Signature')
            ?: request()->header('X-MVola-Signature')
            ?: request()->header('X-Callback-Signature');
        $payload = request()->all();

        if (! $this->mVolaPayment->verifyWebhookSignature(request()->getContent(), $signature)) {
            session()->flash('error', trans('mvola::app.response.verification-failed'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $status = strtoupper($payload['status'] ?? $payload['transactionStatus'] ?? '');

        if ($status === MVolaPayment::STATUS_COMPLETED) {
            $correlationId = $payload['requestingOrganisationTransactionReference']
                ?? $payload['correlation_id']
                ?? ($payload['metadata']['correlation_id'] ?? null);

            if (! $correlationId) {
                session()->flash('error', trans('mvola::app.response.invalid-transaction'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $order = $this->placeOrderFromCallback($correlationId, $payload);

            if (! $order) {
                session()->flash('error', trans('mvola::app.response.order-creation-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            session()->flash('order_id', $order->id);

            session()->flash('success', trans('mvola::app.response.payment-success'));

            return redirect()->route('shop.checkout.onepage.success');
        }

        if ($status === MVolaPayment::STATUS_FAILED) {
            session()->flash('error', trans('mvola::app.response.payment-failed'));
        } else {
            session()->flash('info', trans('mvola::app.response.payment-pending'));
        }

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Cancel payment.
     */
    public function cancel(): RedirectResponse
    {
        session()->flash('error', trans('mvola::app.response.payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Handle server-to-server webhook notifications from MVola.
     */
    public function webhook(): JsonResponse
    {
        $signature = request()->header('X-Signature')
            ?: request()->header('X-MVola-Signature')
            ?: request()->header('X-Callback-Signature');
        $payload = request()->all();

        if (! $this->mVolaPayment->verifyWebhookSignature(request()->getContent(), $signature)) {
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $status = strtoupper($payload['status'] ?? $payload['transactionStatus'] ?? '');

        if ($status !== MVolaPayment::STATUS_COMPLETED) {
            return response()->json(['status' => 'not_completed'], 200);
        }

        $correlationId = $payload['requestingOrganisationTransactionReference']
            ?? $payload['correlation_id']
            ?? ($payload['metadata']['correlation_id'] ?? null);

        if (! $correlationId) {
            return response()->json(['status' => 'invalid_transaction'], 400);
        }

        try {
            $order = $this->placeOrderFromWebhook($correlationId, $payload);

            if (! $order) {
                return response()->json(['status' => 'order_not_placed'], 400);
            }

            return response()->json([
                'status' => 'order_placed',
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Place an order from a callback or webhook notification.
     */
    protected function placeOrderFromCallback(string $correlationId, array $payload): ?OrderContract
    {
        return $this->placeOrder($correlationId, $payload);
    }

    /**
     * Place an order from a webhook notification.
     */
    protected function placeOrderFromWebhook(string $correlationId, array $payload): ?OrderContract
    {
        return $this->placeOrder($correlationId, $payload);
    }

    /**
     * Turn a confirmed MVola payment into an order once.
     */
    protected function placeOrder(string $correlationId, array $payload): ?OrderContract
    {
        $cartId = $this->extractCartId($correlationId);

        if (! $cartId) {
            return null;
        }

        return Cache::lock('mvola.order.'.$cartId, 30)->block(10, function () use ($cartId, $payload, $correlationId) {
            return DB::transaction(function () use ($cartId, $payload, $correlationId) {
                $order = $this->orderRepository->findOneWhere(['cart_id' => $cartId]);

                if ($order && $order->status !== Order::STATUS_PENDING) {
                    return $order;
                }

                $cart = $this->cartRepository->find($cartId);

                if (! $cart || ! $cart->is_active) {
                    return null;
                }

                Cart::setCart($cart);

                core()->setCurrentCurrency($cart->cart_currency_code);

                Cart::collectTotals();

                $cart = Cart::getCart();

                if (
                    ! $cart
                    || ! $this->amountMatches($cart, $payload)
                    || ($order && $order->payment->method !== 'mvola')
                ) {
                    return null;
                }

                if (! $order) {
                    $data = (new OrderResource($cart))->jsonSerialize();

                    $data['payment']['additional'] = [
                        'mvola_correlation_id' => $correlationId,
                        'mvola_status' => strtoupper($payload['status'] ?? $payload['transactionStatus'] ?? ''),
                    ];

                    $order = $this->orderRepository->create($data);
                } else {
                    $order->payment->update([
                        'additional' => array_merge($order->payment->additional ?? [], [
                            'mvola_correlation_id' => $correlationId,
                            'mvola_status' => strtoupper($payload['status'] ?? $payload['transactionStatus'] ?? ''),
                        ]),
                    ]);
                }

                $this->orderRepository->update(['status' => Order::STATUS_PROCESSING], $order->id);

                if ($order->canInvoice()) {
                    $invoice = $this->invoiceRepository->create($this->prepareInvoiceData($order));

                    $this->orderTransactionRepository->create([
                        'transaction_id' => $correlationId,
                        'status' => MVolaPayment::STATUS_COMPLETED,
                        'type' => $order->payment->method,
                        'payment_method' => $order->payment->method,
                        'order_id' => $order->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $order->base_grand_total,
                        'data' => json_encode([
                            'reference' => $correlationId,
                            'status' => strtoupper($payload['status'] ?? $payload['transactionStatus'] ?? ''),
                            'amount' => $payload['amount'] ?? $payload['response']['amount'] ?? null,
                            'currency' => $payload['currency'] ?? $payload['response']['currency'] ?? null,
                        ]),
                    ]);
                }

                Cart::deActivateCart();

                return $order;
            });
        });
    }

    /**
     * Extract the cart ID from the MVola correlation ID metadata.
     */
    protected function extractCartId(string $correlationId): ?int
    {
        $transaction = $this->orderTransactionRepository->findOneWhere([
            'transaction_id' => $correlationId,
            'payment_method' => 'mvola',
        ]);

        if ($transaction) {
            return $transaction->order_id
                ? $this->orderRepository->find($transaction->order_id)->cart_id
                : null;
        }

        $statusData = $this->mVolaPayment->getTransactionStatus($correlationId);

        if (! $statusData) {
            return null;
        }

        $metadata = $statusData['metadata'] ?? $statusData['response']['metadata'] ?? [];

        foreach ($metadata as $item) {
            if (($item['key'] ?? '') === 'cart_id') {
                return (int) $item['value'];
            }
        }

        return null;
    }

    /**
     * Check that MVola reports the same amount as the cart.
     */
    protected function amountMatches($cart, array $payload): bool
    {
        $reportedAmount = $payload['amount'] ?? $payload['response']['amount'] ?? null;
        $reportedCurrency = $payload['currency'] ?? $payload['response']['currency'] ?? null;

        if (
            $reportedAmount === null
            || ! in_array(strtoupper((string) $reportedCurrency), ['MGA', 'AR'], true)
        ) {
            return false;
        }

        return round((float) $reportedAmount) === round((float) $cart->base_grand_total);
    }

    /**
     * Prepare invoice data.
     */
    protected function prepareInvoiceData(OrderContract $order): array
    {
        $invoiceData = ['order_id' => $order->id];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }
}
