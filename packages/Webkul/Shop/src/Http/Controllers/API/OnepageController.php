<?php

namespace Webkul\Shop\Http\Controllers\API;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Webkul\CartRule\Exceptions\CouponUsageLimitExceededException;
use Webkul\Checkout\Facades\Cart;
use Webkul\Customer\Repositories\CustomerRepository;
use Webkul\Payment\Facades\Payment;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Shipping\Facades\Shipping;
use Webkul\Shop\Http\Requests\CartAddressRequest;
use Webkul\Shop\Http\Resources\CartResource;

class OnepageController extends APIController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected CustomerRepository $customerRepository
    ) {}

    /**
     * Return cart summary.
     */
    public function summary(): JsonResource
    {
        $cart = Cart::getCart();

        return new CartResource($cart);
    }

    /**
     * Store address.
     */
    public function storeAddress(CartAddressRequest $cartAddressRequest): JsonResource
    {
        $params = $cartAddressRequest->all();

        if (
            ! auth()->guard('customer')->check()
            && ! Cart::getCart()->hasGuestCheckoutItems()
        ) {
            session()->put('shop.url.intended', route('shop.checkout.onepage.index'));

            return new JsonResource([
                'redirect' => true,
                'data' => route('shop.customer.session.index'),
            ]);
        }

        if (Cart::hasError()) {
            return new JsonResource([
                'redirect' => true,
                'redirect_url' => route('shop.checkout.cart.index'),
            ]);
        }

        Cart::saveAddresses($params);

        $cart = Cart::getCart();

        Cart::collectTotals();

        if ($cart->haveStockableItems()) {
            if (! $rates = Shipping::collectRates()) {
                return new JsonResource([
                    'redirect' => true,
                    'redirect_url' => route('shop.checkout.cart.index'),
                ]);
            }

            return new JsonResource([
                'redirect' => false,
                'data' => $rates,
            ]);
        }

        return new JsonResource([
            'redirect' => false,
            'data' => Payment::getSupportedPaymentMethods(),
        ]);
    }

    /**
     * Store shipping method.
     *
     * @return Response
     */
    public function storeShippingMethod()
    {
        $validatedData = $this->validate(request(), [
            'shipping_method' => 'required',
        ]);

        if (
            Cart::hasError()
            || ! $validatedData['shipping_method']
            || ! Cart::saveShippingMethod($validatedData['shipping_method'])
        ) {
            return response()->json([
                'redirect_url' => route('shop.checkout.cart.index'),
            ], Response::HTTP_FORBIDDEN);
        }

        Cart::collectTotals();

        return response()->json(Payment::getSupportedPaymentMethods());
    }

    /**
     * Store payment method.
     *
     * @return array
     */
    public function storePaymentMethod()
    {
        $validatedData = $this->validate(request(), [
            'payment' => 'required',
        ]);

        if (
            Cart::hasError()
            || ! $validatedData['payment']
            || ! $this->isPaymentMethodAvailable($validatedData['payment']['method'] ?? null)
            || ! Cart::savePaymentMethod($validatedData['payment'])
        ) {
            return response()->json([
                'redirect_url' => route('shop.checkout.cart.index'),
            ], Response::HTTP_FORBIDDEN);
        }

        Cart::collectTotals();

        $cart = Cart::getCart();

        return [
            'cart' => new CartResource($cart),
        ];
    }

    /**
     * Store order
     */
    public function storeOrder()
    {
        try {
            $this->synchronizeCartCurrency();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (Cart::hasError()) {
            return new JsonResource([
                'redirect' => true,
                'redirect_url' => route('shop.checkout.cart.index'),
            ]);
        }

        Cart::collectTotals();

        try {
            $this->validateOrder();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $cart = Cart::getCart();

        $order = $this->findLatestOrderForCart($cart->id);

        if ($order?->status === Order::STATUS_CANCELED) {
            $order = null;
        }

        if ($order && in_array($order->status, [Order::STATUS_PROCESSING, Order::STATUS_COMPLETED], true)) {
            Cart::deActivateCart();

            session()->flash('order_id', $order->id);

            return new JsonResource([
                'redirect' => true,
                'redirect_url' => route('shop.checkout.onepage.success'),
            ]);
        }

        if ($order && $order->status !== Order::STATUS_PENDING) {
            return new JsonResource([
                'redirect' => true,
                'redirect_url' => route('shop.checkout.cart.index'),
            ]);
        }

        if (! $order) {
            $data = (new OrderResource($cart))->jsonSerialize();

            try {
                $order = $this->orderRepository->create($data);
            } catch (CouponUsageLimitExceededException $e) {
                cart()->removeCouponCode();

                Cart::collectTotals();

                return new JsonResource([
                    'redirect' => false,
                    'message' => trans('shop::app.checkout.coupon.usage-limit-exceeded'),
                ]);
            }
        }

        if ($redirectUrl = Payment::getRedirectUrl($cart)) {
            return new JsonResource([
                'redirect' => true,
                'redirect_url' => $redirectUrl,
            ]);
        }

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

        return new JsonResource([
            'redirect' => true,
            'redirect_url' => route('shop.checkout.onepage.success'),
        ]);
    }

    /**
     * Validate order before creation.
     *
     * @return void|\Exception
     */
    public function validateOrder()
    {
        $cart = Cart::getCart();

        $minimumOrderAmount = core()->getConfigData('sales.order_settings.minimum_order.minimum_order_amount') ?: 0;

        if (
            auth()->guard('customer')->check()
            && auth()->guard('customer')->user()->is_suspended
        ) {
            throw new \Exception(trans('shop::app.checkout.cart.suspended-account-message'));
        }

        if (
            auth()->guard('customer')->user()
            && ! auth()->guard('customer')->user()->status
        ) {
            throw new \Exception(trans('shop::app.checkout.cart.inactive-account-message'));
        }

        if (! Cart::haveMinimumOrderAmount()) {
            throw new \Exception(trans('shop::app.checkout.cart.minimum-order-message', ['amount' => core()->currency($minimumOrderAmount)]));
        }

        if ($cart->haveStockableItems() && ! $cart->shipping_address) {
            throw new \Exception(trans('shop::app.checkout.onepage.address.check-shipping-address'));
        }

        if (! $cart->billing_address) {
            throw new \Exception(trans('shop::app.checkout.onepage.address.check-billing-address'));
        }

        if (
            $cart->haveStockableItems()
            && ! $cart->selected_shipping_rate
        ) {
            throw new \Exception(trans('shop::app.checkout.cart.specify-shipping-method'));
        }

        if (! $cart->payment) {
            throw new \Exception(trans('shop::app.checkout.cart.specify-payment-method'));
        }

        if ($cart->payment->method === 'paypal_smart_button') {
            throw new \Exception(trans('shop::app.checkout.cart.specify-payment-method'));
        }

        if (! $this->isPaymentMethodAvailable($cart->payment->method)) {
            throw new \Exception(trans('shop::app.checkout.cart.specify-payment-method'));
        }
    }

    /**
     * Checks whether the given payment method is available for the current cart.
     *
     * Availability is evaluated server-side against the cart contents (e.g. Cash
     * On Delivery is not available for non-stockable/downloadable carts). This
     * prevents crafted requests from bypassing the front-end method filtering.
     */
    protected function isPaymentMethodAvailable(?string $method): bool
    {
        if (! $method) {
            return false;
        }

        return Payment::isPaymentMethodAvailable($method);
    }

    /**
     * Rebase a cart left behind by the store's previous currency configuration.
     */
    protected function synchronizeCartCurrency(): void
    {
        Cart::refreshCart();

        $cart = Cart::getCart();
        $currencyCode = core()->getChannelBaseCurrencyCode();

        if (! $cart || ! $currencyCode) {
            return;
        }

        if (! in_array($currencyCode, config('app.allowed_currencies', []), true)) {
            throw new \RuntimeException($this->unsupportedCartCurrencyMessage($cart, $currencyCode));
        }

        core()->setCurrentCurrency($currencyCode);

        if (
            $cart->global_currency_code === $currencyCode
            && $cart->base_currency_code === $currencyCode
            && $cart->channel_currency_code === $currencyCode
            && $cart->cart_currency_code === $currencyCode
        ) {
            return;
        }

        foreach ([
            'grand_total' => 'base_grand_total',
            'sub_total' => 'base_sub_total',
            'tax_total' => 'base_tax_total',
            'discount_amount' => 'base_discount_amount',
            'shipping_amount' => 'base_shipping_amount',
            'shipping_amount_incl_tax' => 'base_shipping_amount_incl_tax',
            'sub_total_incl_tax' => 'base_sub_total_incl_tax',
        ] as $amountField => $baseAmountField) {
            if ((float) $cart->{$amountField} !== (float) $cart->{$baseAmountField}) {
                throw new \RuntimeException($this->unsupportedCartCurrencyMessage($cart, $currencyCode));
            }
        }

        foreach ($cart->all_items as $item) {
            if ((float) $item->quantity <= 0) {
                continue;
            }

            if ((float) $item->price !== (float) $item->base_price) {
                throw new \RuntimeException($this->unsupportedCartCurrencyMessage($cart, $currencyCode));
            }
        }

        $order = $this->findLatestOrderForCart($cart->id);

        DB::transaction(function () use ($cart, $currencyCode, $order) {
            if (
                $order
                && $order->status === Order::STATUS_PENDING
                && $order->base_currency_code !== $currencyCode
            ) {
                if (
                    ! in_array($order->payment?->method, config('app.allowed_payment_methods', []), true)
                    || $order->invoices()->exists()
                    || $order->transactions()->exists()
                    || ! $this->orderRepository->cancel($order->id, force: true)
                ) {
                    throw new \RuntimeException($this->unsupportedCartCurrencyMessage($cart, $currencyCode));
                }
            }

            $cart->update([
                'global_currency_code' => $currencyCode,
                'base_currency_code' => $currencyCode,
                'channel_currency_code' => $currencyCode,
                'cart_currency_code' => $currencyCode,
                'exchange_rate' => 1,
            ]);
        });

        Cart::refreshCart();
    }

    /**
     * Explain why a cart with stale currency data cannot be submitted.
     */
    protected function unsupportedCartCurrencyMessage($cart, string $currencyCode): string
    {
        return trans('mvola::app.response.supported-currency-error', [
            'currency' => $cart->base_currency_code,
            'supportedCurrencies' => $currencyCode,
        ]);
    }

    /**
     * Get the most recent order for a cart.
     */
    protected function findLatestOrderForCart(int $cartId): ?OrderContract
    {
        return $this->orderRepository
            ->findWhere(['cart_id' => $cartId])
            ->sortByDesc('id')
            ->first();
    }
}
