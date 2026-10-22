<?php

namespace Webkul\OrangeMoney\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\OrangeMoney\Payment\OrangeMoneyPayment;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

class OrangeMoneyController extends Controller
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
        protected OrangeMoneyPayment $orangeMoneyPayment,
    ) {}

    /**
     * Redirect to the Orange Money payment page.
     */
    public function redirect(): RedirectResponse|View
    {
        if (! $this->orangeMoneyPayment->isAvailable()) {
            session()->flash('error', trans('orange_money::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $cart = Cart::getCart();

        if (! $cart) {
            session()->flash('error', trans('orange_money::app.response.cart-not-found'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $currency = strtoupper($cart->base_currency_code ?? core()->getBaseCurrencyCode());

        if (! $this->orangeMoneyPayment->isCurrencySupported($currency)) {
            session()->flash('error', trans('orange_money::app.response.supported-currency-error', [
                'currency' => $currency,
                'supportedCurrencies' => implode(', ', $this->orangeMoneyPayment->getSupportedCurrencies()),
            ]));

            return redirect()->route('shop.checkout.cart.index');
        }

        return view('orange_money::checkout.redirect', [
            'amount' => $cart->base_grand_total,
            'currency' => $currency,
            'customerPhone' => $cart->billing_address?->phone ?? '',
            'paymentStarted' => $this->hasRecentPaymentAttempt('orange_money', $cart->id),
            'transactionReference' => session('orange_money.payment.reference'),
        ]);
    }

    /**
     * Initiate an Orange Money payment for the active cart.
     */
    public function pay(): RedirectResponse
    {
        $validated = request()->validate([
            'customer_phone' => ['required', 'string', 'regex:/^(?:\\+?261|0)?3[23478][0-9]{7}$/'],
        ]);

        $cart = Cart::getCart();

        if (
            ! $this->orangeMoneyPayment->isAvailable()
            || ! $cart
            || $cart->payment?->method !== 'orange_money'
        ) {
            session()->flash('error', trans('orange_money::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        if ($this->hasRecentPaymentAttempt('orange_money', $cart->id)) {
            session()->flash('info', trans('orange_money::app.response.payment-pending'));

            return redirect()->route('orange_money.redirect');
        }

        try {
            $payment = $this->orangeMoneyPayment->initiatePayment($cart, $validated['customer_phone']);
        } catch (ConnectionException $exception) {
            report($exception);

            session()->flash('error', trans('orange_money::app.response.payment-failed'));

            return redirect()->route('orange_money.redirect');
        }

        if (! $payment || empty($payment['order_id'])) {
            session()->flash('error', trans('orange_money::app.response.payment-failed'));

            return redirect()->route('orange_money.redirect');
        }

        session()->put('orange_money.payment', [
            'cart_id' => $cart->id,
            'reference' => $payment['order_id'],
            'created_at' => now()->timestamp,
        ]);

        session()->flash('info', trans('orange_money::app.response.payment-pending'));

        return redirect()->route('orange_money.redirect');
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
     * Handle Orange Money payment callback.
     *
     * Orange Money sends a POST notification with the payment status after
     * the customer completes or cancels the payment on their mobile device.
     */
    public function callback(): RedirectResponse
    {
        $signature = request()->header('X-Orange-Signature')
            ?: request()->header('X-Signature')
            ?: request()->header('X-Callback-Signature');
        $payload = request()->all();

        if (! $this->orangeMoneyPayment->verifyWebhookSignature(request()->getContent(), $signature)) {
            session()->flash('error', trans('orange_money::app.response.verification-failed'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $status = strtoupper($payload['status'] ?? $payload['payment']['status'] ?? '');

        if ($status === OrangeMoneyPayment::STATUS_COMPLETED) {
            $orderId = $payload['order']['orderId'] ?? $payload['order_id'] ?? null;

            if (! $orderId) {
                session()->flash('error', trans('orange_money::app.response.invalid-transaction'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $order = $this->placeOrderFromCallback($orderId, $payload);

            if (! $order) {
                session()->flash('error', trans('orange_money::app.response.order-creation-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            session()->flash('order_id', $order->id);

            session()->flash('success', trans('orange_money::app.response.payment-success'));

            return redirect()->route('shop.checkout.onepage.success');
        }

        if ($status === OrangeMoneyPayment::STATUS_FAILED) {
            session()->flash('error', trans('orange_money::app.response.payment-failed'));
        } else {
            session()->flash('info', trans('orange_money::app.response.payment-pending'));
        }

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Cancel payment.
     */
    public function cancel(): RedirectResponse
    {
        session()->flash('error', trans('orange_money::app.response.payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Handle server-to-server webhook notifications from Orange Money.
     */
    public function webhook(): JsonResponse
    {
        $signature = request()->header('X-Orange-Signature')
            ?: request()->header('X-Signature')
            ?: request()->header('X-Callback-Signature');
        $payload = request()->getContent();

        if (! $this->orangeMoneyPayment->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        $payloadData = json_decode($payload, true);

        if (! is_array($payloadData)) {
            return response()->json(['status' => 'invalid_payload'], 400);
        }
        $status = strtoupper($payloadData['status'] ?? $payloadData['payment']['status'] ?? '');

        if ($status !== OrangeMoneyPayment::STATUS_COMPLETED) {
            return response()->json(['status' => 'not_completed'], 200);
        }

        $orderId = $payloadData['order']['orderId'] ?? $payloadData['order_id'] ?? null;

        if (! $orderId) {
            return response()->json(['status' => 'invalid_transaction'], 400);
        }

        try {
            $order = $this->placeOrderFromWebhook($orderId, $payloadData);

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
    protected function placeOrderFromCallback(string $orderId, array $payload): ?OrderContract
    {
        return $this->placeOrder($orderId, $payload);
    }

    /**
     * Place an order from a webhook notification.
     */
    protected function placeOrderFromWebhook(string $orderId, array $payload): ?OrderContract
    {
        return $this->placeOrder($orderId, $payload);
    }

    /**
     * Turn a confirmed Orange Money payment into an order once.
     */
    protected function placeOrder(string $orderId, array $payload): ?OrderContract
    {
        $cartId = $this->extractCartId($orderId);

        if (! $cartId) {
            return null;
        }

        return Cache::lock('orange_money.order.'.$cartId, 30)->block(10, function () use ($cartId, $payload, $orderId) {
            return DB::transaction(function () use ($cartId, $payload, $orderId) {
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
                    || ($order && $order->payment->method !== 'orange_money')
                ) {
                    return null;
                }

                if (! $order) {
                    $data = (new OrderResource($cart))->jsonSerialize();

                    $data['payment']['additional'] = [
                        'orange_money_order_id' => $orderId,
                        'orange_money_status' => strtoupper($payload['status'] ?? $payload['payment']['status'] ?? ''),
                    ];

                    $order = $this->orderRepository->create($data);
                } else {
                    $order->payment->update([
                        'additional' => array_merge($order->payment->additional ?? [], [
                            'orange_money_order_id' => $orderId,
                            'orange_money_status' => strtoupper($payload['status'] ?? $payload['payment']['status'] ?? ''),
                        ]),
                    ]);
                }

                $this->orderRepository->update(['status' => Order::STATUS_PROCESSING], $order->id);

                if ($order->canInvoice()) {
                    $invoice = $this->invoiceRepository->create($this->prepareInvoiceData($order));

                    $this->orderTransactionRepository->create([
                        'transaction_id' => $orderId,
                        'status' => OrangeMoneyPayment::STATUS_COMPLETED,
                        'type' => $order->payment->method,
                        'payment_method' => $order->payment->method,
                        'order_id' => $order->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $order->base_grand_total,
                        'data' => json_encode([
                            'reference' => $orderId,
                            'status' => strtoupper($payload['status'] ?? $payload['payment']['status'] ?? ''),
                            'amount' => $payload['order']['amount'] ?? $payload['amount'] ?? $payload['data']['amount'] ?? null,
                            'currency' => $payload['order']['currency'] ?? $payload['currency'] ?? $payload['data']['currency'] ?? null,
                        ]),
                    ]);
                }

                Cart::deActivateCart();

                return $order;
            });
        });
    }

    /**
     * Extract the cart ID from the Orange Money order ID or status data.
     */
    protected function extractCartId(string $orderId): ?int
    {
        if (preg_match('/^ORANGE_(\d+)_/', $orderId, $matches)) {
            return (int) $matches[1];
        }

        $transaction = $this->orderTransactionRepository->findOneWhere([
            'transaction_id' => $orderId,
            'payment_method' => 'orange_money',
        ]);

        if ($transaction && $transaction->order_id) {
            $order = $this->orderRepository->find($transaction->order_id);

            return $order ? $order->cart_id : null;
        }

        $statusData = $this->orangeMoneyPayment->getPaymentStatus($orderId);

        if (! $statusData) {
            return null;
        }

        $metadata = $statusData['order']['metadata'] ?? $statusData['metadata'] ?? null;

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (isset($metadata['cart_id'])) {
            return (int) $metadata['cart_id'];
        }

        return null;
    }

    /**
     * Check that Orange Money reports the same amount as the cart.
     */
    protected function amountMatches($cart, array $payload): bool
    {
        $reportedAmount = $payload['order']['amount']
            ?? $payload['amount']
            ?? $payload['data']['amount']
            ?? null;
        $reportedCurrency = $payload['order']['currency']
            ?? $payload['currency']
            ?? $payload['data']['currency']
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
