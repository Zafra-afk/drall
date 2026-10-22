<?php

namespace Webkul\MalagasyCard\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\MalagasyCard\Payment\MalagasyCardPayment;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shop\Http\Controllers\Controller;

class MalagasyCardController extends Controller
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
        protected MalagasyCardPayment $malagasyCardPayment,
    ) {}

    /**
     * Redirect the customer to Papi's hosted card payment page.
     */
    public function redirect(): RedirectResponse
    {
        if (! $this->malagasyCardPayment->hasValidCredentials()) {
            session()->flash('error', trans('malagasy_card::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $cart = Cart::getCart();

        if (! $cart) {
            session()->flash('error', trans('malagasy_card::app.response.cart-not-found'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $currency = $this->malagasyCardPayment->resolveCurrency($cart);

        if (! $this->malagasyCardPayment->isCurrencySupported($currency)) {
            session()->flash('error', trans('malagasy_card::app.response.supported-currency-error', [
                'currency' => $currency,
                'supportedCurrencies' => implode(', ', $this->malagasyCardPayment->getSupportedCurrencies()),
            ]));

            return redirect()->route('shop.checkout.cart.index');
        }

        try {
            $paymentLink = $this->malagasyCardPayment->createPaymentLink($cart);

            if (! $paymentLink || empty($paymentLink['link'])) {
                session()->flash('error', trans('malagasy_card::app.response.payment-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            Cache::put('malagasy_card.payment.'.$paymentLink['reference'], $cart->id, 3600);

            return redirect()->away($paymentLink['link']);
        } catch (\Throwable $e) {
            report($e);

            session()->flash('error', trans('malagasy_card::app.response.payment-failed'));

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    /**
     * Handle the customer returning from Papi's hosted payment page.
     *
     * The callback verifies the payment with Papi's read-back API before
     * placing the order, so a tampered return URL cannot place an order.
     */
    public function callback(): RedirectResponse
    {
        $reference = request()->input('reference') ?? request()->query('reference');

        if (! $reference) {
            session()->flash('error', trans('malagasy_card::app.response.invalid-transaction'));

            return redirect()->route('shop.checkout.cart.index');
        }

        try {
            $verification = $this->malagasyCardPayment->fetchPayment($reference);

            if (! $verification || ! $this->isPaymentSuccessful($verification)) {
                session()->flash('error', trans('malagasy_card::app.response.verification-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $cartId = Cache::get('malagasy_card.payment.'.$reference);

            if (! $cartId) {
                session()->flash('error', trans('malagasy_card::app.response.cart-not-found'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $order = $this->placeOrder($cartId, $verification);

            if (! $order) {
                session()->flash('error', trans('malagasy_card::app.response.order-creation-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            Cache::forget('malagasy_card.payment.'.$reference);

            session()->flash('order_id', $order->id);

            session()->flash('success', trans('malagasy_card::app.response.payment-success'));

            return redirect()->route('shop.checkout.onepage.success');
        } catch (\Throwable $e) {
            report($e);

            session()->flash('error', trans('malagasy_card::app.response.verification-failed'));

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    /**
     * Cancel payment.
     */
    public function cancel(): RedirectResponse
    {
        session()->flash('error', trans('malagasy_card::app.response.payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Handle server-to-server webhook notifications from Papi.
     *
     * Each notification is verified with the HMAC-SHA256 signature carried in
     * the X-Papi-Signature header, including 300-second replay protection.
     */
    public function webhook(): JsonResponse
    {
        $payload = request()->getContent();

        $signature = request()->header('X-Papi-Signature');

        if (! $this->malagasyCardPayment->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['status' => 'invalid_signature'], 401);
        }

        $data = json_decode($payload, true) ?? [];

        if (! $this->isPaymentSuccessful($data)) {
            return response()->json(['status' => 'not_completed'], 200);
        }

        $reference = $data['merchantPaymentReference'] ?? null;

        if (! $reference) {
            return response()->json(['status' => 'invalid_transaction'], 400);
        }

        $cartId = Cache::get('malagasy_card.payment.'.$reference);

        if (! $cartId) {
            return response()->json(['status' => 'cart_not_found'], 200);
        }

        try {
            $order = $this->placeOrder($cartId, ['data' => $data]);

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
     * Determine whether a Papi payment response reports a successful payment.
     *
     * @param  array  $response
     */
    protected function isPaymentSuccessful($response): bool
    {
        $status = $response['data']['paymentStatus'] ?? $response['paymentStatus'] ?? '';

        return strtoupper((string) $status) === MalagasyCardPayment::STATUS_SUCCESSFUL;
    }

    /**
     * Place an order from a callback or webhook notification.
     */
    protected function placeOrder(int $cartId, array $verification): ?OrderContract
    {
        return Cache::lock('malagasy_card.order.'.$cartId, 30)->block(10, function () use ($cartId, $verification) {
            if ($order = $this->orderRepository->findOneWhere(['cart_id' => $cartId])) {
                return $order;
            }

            $cart = $this->cartRepository->find($cartId);

            if (
                ! $cart
                || ! $cart->is_active
            ) {
                return null;
            }

            Cart::setCart($cart);

            core()->setCurrentCurrency($cart->cart_currency_code);

            Cart::collectTotals();

            $cart = Cart::getCart();

            if (
                ! $cart
                || ! $this->amountMatches($cart, $verification)
            ) {
                return null;
            }

            $data = (new OrderResource($cart))->jsonSerialize();

            $transactionId = $verification['data']['papiPaymentReference']
                ?? $verification['data']['paymentReference']
                ?? $verification['paymentReference']
                ?? '';

            $merchantReference = $verification['data']['merchantPaymentReference']
                ?? $verification['merchantPaymentReference']
                ?? '';

            $data['payment']['additional'] = [
                'papi_payment_reference' => (string) $transactionId,
                'papi_merchant_reference' => (string) $merchantReference,
                'papi_payment_status' => (string) ($verification['data']['paymentStatus'] ?? $verification['paymentStatus'] ?? ''),
                'papi_notification_token' => (string) ($verification['data']['notificationToken'] ?? $verification['notificationToken'] ?? ''),
                'papi_response' => json_encode($verification),
            ];

            $order = $this->orderRepository->create($data);

            $this->orderRepository->update(['status' => Order::STATUS_PROCESSING], $order->id);

            if ($order->canInvoice()) {
                $invoice = $this->invoiceRepository->create($this->prepareInvoiceData($order));

                $this->orderTransactionRepository->create([
                    'transaction_id' => (string) $transactionId,
                    'status' => MalagasyCardPayment::STATUS_SUCCESSFUL,
                    'type' => $order->payment->method,
                    'payment_method' => $order->payment->method,
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $order->base_grand_total,
                    'data' => json_encode($verification),
                ]);
            }

            Cart::deActivateCart();

            return $order;
        });
    }

    /**
     * Check that Papi reports the same amount and currency as the cart.
     *
     * @param  object  $cart
     */
    protected function amountMatches($cart, array $verification): bool
    {
        $reportedAmount = $verification['data']['amount'] ?? null;

        if ($reportedAmount === null) {
            return false;
        }

        $reportedCurrency = strtoupper($verification['data']['currency'] ?? '');

        $expectedCurrency = $this->malagasyCardPayment->resolveCurrency($cart);

        if ($reportedCurrency !== $expectedCurrency) {
            return false;
        }

        return round((float) $reportedAmount, 2) === round((float) $cart->base_grand_total, 2);
    }

    /**
     * Prepare the invoice data for every item still to be invoiced.
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
