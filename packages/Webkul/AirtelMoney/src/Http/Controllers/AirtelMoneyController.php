<?php

namespace Webkul\AirtelMoney\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\AirtelMoney\Payment\AirtelMoneyPayment;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

class AirtelMoneyController extends Controller
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
        protected AirtelMoneyPayment $airtelMoneyPayment,
    ) {}

    /**
     * Redirect to the Airtel Money payment page.
     */
    public function redirect(): RedirectResponse|View
    {
        if (! $this->airtelMoneyPayment->isAvailable()) {
            session()->flash('error', trans('airtel_money::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $cart = Cart::getCart();

        if (! $cart) {
            session()->flash('error', trans('airtel_money::app.response.cart-not-found'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $currency = strtoupper($cart->base_currency_code ?? core()->getBaseCurrencyCode());

        if (! $this->airtelMoneyPayment->isCurrencySupported($currency)) {
            session()->flash('error', trans('airtel_money::app.response.supported-currency-error', [
                'currency' => $currency,
                'supportedCurrencies' => implode(', ', $this->airtelMoneyPayment->getSupportedCurrencies()),
            ]));

            return redirect()->route('shop.checkout.cart.index');
        }

        return view('airtel_money::checkout.redirect', [
            'amount' => $cart->base_grand_total,
            'currency' => $currency,
            'customerPhone' => $cart->billing_address?->phone ?? '',
            'paymentStarted' => $this->hasRecentPaymentAttempt('airtel_money', $cart->id),
            'transactionReference' => session('airtel_money.payment.reference'),
        ]);
    }

    /**
     * Initiate an Airtel Money payment for the active cart.
     */
    public function pay(): RedirectResponse
    {
        $validated = request()->validate([
            'customer_phone' => ['required', 'string', 'regex:/^(?:\\+?261|0)?3[23478][0-9]{7}$/'],
        ]);

        $cart = Cart::getCart();

        if (
            ! $this->airtelMoneyPayment->isAvailable()
            || ! $cart
            || $cart->payment?->method !== 'airtel_money'
        ) {
            session()->flash('error', trans('airtel_money::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        if ($this->hasRecentPaymentAttempt('airtel_money', $cart->id)) {
            session()->flash('info', trans('airtel_money::app.response.payment-pending'));

            return redirect()->route('airtel_money.redirect');
        }

        try {
            $payment = $this->airtelMoneyPayment->initiatePayment($cart, $validated['customer_phone']);
        } catch (ConnectionException $exception) {
            report($exception);

            session()->flash('error', trans('airtel_money::app.response.payment-failed'));

            return redirect()->route('airtel_money.redirect');
        }

        if (! $payment || empty($payment['reference'])) {
            session()->flash('error', trans('airtel_money::app.response.payment-failed'));

            return redirect()->route('airtel_money.redirect');
        }

        session()->put('airtel_money.payment', [
            'cart_id' => $cart->id,
            'reference' => $payment['reference'],
            'created_at' => now()->timestamp,
        ]);

        session()->flash('info', trans('airtel_money::app.response.payment-pending'));

        return redirect()->route('airtel_money.redirect');
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
     * Handle Airtel Money payment callback.
     *
     * Airtel Money sends a POST notification with the payment status after
     * the customer completes or cancels the payment on their mobile device.
     */
    public function callback(): RedirectResponse
    {
        $signature = request()->header('X-Airtel-Signature')
            ?: request()->header('X-Signature')
            ?: request()->header('X-Callback-Signature');
        $payload = request()->all();

        if (! $this->airtelMoneyPayment->verifyWebhookSignature(request()->getContent(), $signature)) {
            session()->flash('error', trans('airtel_money::app.response.verification-failed'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $status = $this->airtelMoneyPayment->resolveTransactionStatus($payload);

        if ($status === AirtelMoneyPayment::STATUS_COMPLETED) {
            $transactionId = $payload['reference']
                ?? $payload['data']['transaction']['id']
                ?? $payload['transaction_id']
                ?? null;

            if (! $transactionId) {
                session()->flash('error', trans('airtel_money::app.response.invalid-transaction'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $order = $this->placeOrderFromCallback($transactionId, $payload);

            if (! $order) {
                session()->flash('error', trans('airtel_money::app.response.order-creation-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            session()->flash('order_id', $order->id);

            session()->flash('success', trans('airtel_money::app.response.payment-success'));

            return redirect()->route('shop.checkout.onepage.success');
        }

        if ($status === AirtelMoneyPayment::STATUS_FAILED) {
            session()->flash('error', trans('airtel_money::app.response.payment-failed'));
        } else {
            session()->flash('info', trans('airtel_money::app.response.payment-pending'));
        }

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Cancel payment.
     */
    public function cancel(): RedirectResponse
    {
        session()->flash('error', trans('airtel_money::app.response.payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Handle server-to-server webhook notifications from Airtel Money.
     */
    public function webhook(): JsonResponse
    {
        $signature = request()->header('X-Airtel-Signature')
            ?: request()->header('X-Signature')
            ?: request()->header('X-Callback-Signature');
        $payload = request()->getContent();

        if (! $this->airtelMoneyPayment->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payloadData = json_decode($payload, true);

        if (! is_array($payloadData)) {
            return response()->json(['status' => 'invalid_payload'], 400);
        }
        $status = $this->airtelMoneyPayment->resolveTransactionStatus($payloadData);

        if ($status !== AirtelMoneyPayment::STATUS_COMPLETED) {
            return response()->json(['status' => 'not_completed'], 200);
        }

        $transactionId = $payloadData['reference']
            ?? $payloadData['data']['transaction']['id']
            ?? $payloadData['transaction_id']
            ?? null;

        if (! $transactionId) {
            return response()->json(['status' => 'invalid_transaction'], 400);
        }

        try {
            $order = $this->placeOrderFromWebhook($transactionId, $payloadData);

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
     * Place an order from a callback notification.
     */
    protected function placeOrderFromCallback(string $transactionId, array $payload): ?OrderContract
    {
        $cartId = $this->extractCartId($transactionId);

        if (! $cartId) {
            return null;
        }

        return $this->placeOrder($cartId, $payload, $transactionId);
    }

    /**
     * Place an order from a webhook notification.
     */
    protected function placeOrderFromWebhook(string $transactionId, array $payload): ?OrderContract
    {
        return $this->placeOrderFromCallback($transactionId, $payload);
    }

    /**
     * Turn a confirmed Airtel Money payment into an order once.
     */
    protected function placeOrder(int $cartId, array $payload, string $transactionId): ?OrderContract
    {
        return Cache::lock('airtel_money.order.'.$cartId, 30)->block(10, function () use ($cartId, $payload, $transactionId) {
            return DB::transaction(function () use ($cartId, $payload, $transactionId) {
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
                    || ($order && $order->payment->method !== 'airtel_money')
                ) {
                    return null;
                }

                if (! $order) {
                    $data = (new OrderResource($cart))->jsonSerialize();

                    $data['payment']['additional'] = [
                        'airtel_money_reference' => $transactionId,
                        'airtel_money_status' => $this->airtelMoneyPayment->resolveTransactionStatus($payload) ?? '',
                    ];

                    $order = $this->orderRepository->create($data);
                } else {
                    $order->payment->update([
                        'additional' => array_merge($order->payment->additional ?? [], [
                            'airtel_money_reference' => $transactionId,
                            'airtel_money_status' => $this->airtelMoneyPayment->resolveTransactionStatus($payload) ?? '',
                        ]),
                    ]);
                }

                $this->orderRepository->update(['status' => Order::STATUS_PROCESSING], $order->id);

                if ($order->canInvoice()) {
                    $invoice = $this->invoiceRepository->create($this->prepareInvoiceData($order));

                    $this->orderTransactionRepository->create([
                        'transaction_id' => $transactionId,
                        'status' => AirtelMoneyPayment::STATUS_COMPLETED,
                        'type' => $order->payment->method,
                        'payment_method' => $order->payment->method,
                        'order_id' => $order->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $order->base_grand_total,
                        'data' => json_encode([
                            'reference' => $transactionId,
                            'status' => $this->airtelMoneyPayment->resolveTransactionStatus($payload) ?? '',
                            'amount' => $payload['data']['transaction']['amount'] ?? $payload['amount'] ?? null,
                            'currency' => $payload['data']['transaction']['currency'] ?? $payload['currency'] ?? null,
                        ]),
                    ]);
                }

                Cart::deActivateCart();

                return $order;
            });
        });
    }

    /**
     * Find the cart ID associated with a transaction from the stored metadata.
     *
     * Airtel Money does not store the cart ID directly, so we look it up
     * from the order transaction created during the callback flow.
     */
    protected function extractCartId(string $transactionId): ?int
    {
        if (preg_match('/^AIRTEL_[A-Z0-9]+_(\d+)$/i', $transactionId, $matches)) {
            return (int) $matches[1];
        }

        $transaction = $this->orderTransactionRepository->findOneWhere([
            'transaction_id' => $transactionId,
            'payment_method' => 'airtel_money',
        ]);

        if ($transaction && $transaction->order_id) {
            $order = $this->orderRepository->find($transaction->order_id);

            return $order ? $order->cart_id : null;
        }

        return null;
    }

    /**
     * Check that Airtel Money reports the same amount as the cart.
     */
    protected function amountMatches($cart, array $payload): bool
    {
        $reportedAmount = $payload['data']['transaction']['amount']
            ?? $payload['amount']
            ?? null;
        $reportedCurrency = $payload['data']['transaction']['currency']
            ?? $payload['currency']
            ?? null;

        if ($reportedAmount === null || strtoupper((string) $reportedCurrency) !== 'MGA') {
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
