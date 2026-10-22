<?php

use Webkul\AirtelMoney\Http\Controllers\AirtelMoneyController;
use Webkul\AirtelMoney\Payment\AirtelMoneyPayment;
use Webkul\MalagasyCard\Http\Controllers\MalagasyCardController;
use Webkul\MalagasyCard\Payment\MalagasyCardPayment;
use Webkul\MVola\Http\Controllers\MVolaController;
use Webkul\MVola\Payment\MVolaPayment;
use Webkul\OrangeMoney\Http\Controllers\OrangeMoneyController;
use Webkul\OrangeMoney\Payment\OrangeMoneyPayment;
use Webkul\Payment\Facades\Payment;

use function Pest\Laravel\postJson;

// ============================================================================
// Payment Confirmation
// ============================================================================

it('should reject successful payment notifications that omit the paid amount', function (string $controllerClass) {
    $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($controllerClass, 'amountMatches');

    expect($method->invoke($controller, (object) ['base_grand_total' => 25000], []))->toBeFalse();
})->with([
    'MVola' => MVolaController::class,
    'Orange Money' => OrangeMoneyController::class,
    'Airtel Money' => AirtelMoneyController::class,
    'Papi' => MalagasyCardController::class,
]);

it('should reject mobile payment notifications with a non-Malagasy currency', function (string $controllerClass, array $payload) {
    $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($controllerClass, 'amountMatches');

    expect($method->invoke($controller, (object) ['base_grand_total' => 25000], $payload))->toBeFalse();
})->with([
    'MVola' => [MVolaController::class, ['amount' => 25000, 'currency' => 'USD']],
    'Orange Money' => [OrangeMoneyController::class, ['order' => ['amount' => 25000, 'currency' => 'USD']]],
    'Airtel Money' => [AirtelMoneyController::class, ['data' => ['transaction' => ['amount' => 25000, 'currency' => 'USD']]]],
]);

it('should accept matching payment amounts only in each provider currency', function (string $controllerClass, array $payload) {
    $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($controllerClass, 'amountMatches');

    expect($method->invoke($controller, (object) ['base_grand_total' => 25000], $payload))->toBeTrue();
})->with([
    'MVola' => [MVolaController::class, ['amount' => 25000, 'currency' => 'Ar']],
    'Orange Money' => [OrangeMoneyController::class, ['order' => ['amount' => 25000, 'currency' => 'MGA']]],
    'Airtel Money' => [AirtelMoneyController::class, ['data' => ['transaction' => ['amount' => 25000, 'currency' => 'MGA']]]],
]);

it('should reject unsigned mobile-money callbacks without an internal server error', function (string $routeName) {
    postJson(route($routeName), ['status' => 'completed'])
        ->assertBadRequest()
        ->assertJsonPath('status', 'invalid_signature');
})->with([
    'Orange Money' => 'orange_money.webhook',
    'Airtel Money' => 'airtel_money.webhook',
]);

it('should not treat an unsupported cart currency as Malagasy ariary', function () {
    $payment = new MalagasyCardPayment;

    expect($payment->resolveCurrency((object) ['base_currency_code' => 'USD']))->toBeNull()
        ->and($payment->resolveCurrency((object) ['base_currency_code' => 'MGA']))->toBe('MGA');
});

it('should reject Papi confirmations with no currency or a different currency', function () {
    $controller = (new ReflectionClass(MalagasyCardController::class))->newInstanceWithoutConstructor();
    $payment = Mockery::mock(MalagasyCardPayment::class);
    $payment->shouldReceive('resolveCurrency')->andReturn('MGA');

    $property = new ReflectionProperty(MalagasyCardController::class, 'malagasyCardPayment');
    $property->setValue($controller, $payment);

    $method = new ReflectionMethod(MalagasyCardController::class, 'amountMatches');

    expect($method->invoke($controller, (object) ['base_grand_total' => 25000], [
        'data' => ['amount' => 25000],
    ]))->toBeFalse()
        ->and($method->invoke($controller, (object) ['base_grand_total' => 25000], [
            'data' => ['amount' => 25000, 'currency' => 'USD'],
        ]))->toBeFalse()
        ->and($method->invoke($controller, (object) ['base_grand_total' => 25000], [
            'data' => ['amount' => 25000, 'currency' => 'MGA'],
        ]))->toBeTrue();
});

// ============================================================================
// Mobile Payment Checkout
// ============================================================================

it('should not require provider logos to build the payment method list', function (string $paymentClass) {
    $payment = Mockery::mock($paymentClass)->makePartial();
    $payment->shouldReceive('getConfigData')
        ->with('image')
        ->once()
        ->andReturn(null);

    expect($payment->getImage())->toBeNull();
})->with([
    'MVola' => MVolaPayment::class,
    'Orange Money' => OrangeMoneyPayment::class,
    'Airtel Money' => AirtelMoneyPayment::class,
]);

it('should keep each mobile payment method unavailable when credentials are missing', function (string $code, string $paymentClass) {
    config(["payment_methods.$code.active" => true]);

    $payment = Mockery::mock($paymentClass)->makePartial();
    $payment->shouldReceive('getConfigData')->andReturn(null);

    expect($payment->isAvailable())->toBeFalse();
})->with([
    'MVola' => ['mvola', MVolaPayment::class],
    'Orange Money' => ['orange_money', OrangeMoneyPayment::class],
    'Airtel Money' => ['airtel_money', AirtelMoneyPayment::class],
]);

it('should expose only the three Malagasy connectors and mark missing-credential methods unavailable', function () {
    $this->setConfig([
        'sales.payment_methods.mvola.consumer_key' => '',
        'sales.payment_methods.mvola.consumer_secret' => '',
        'sales.payment_methods.mvola.merchant_number' => '',
        'sales.payment_methods.mvola.partner_name' => '',
        'sales.payment_methods.mvola.webhook_secret' => '',
        'sales.payment_methods.orange_money.client_id' => '',
        'sales.payment_methods.orange_money.client_secret' => '',
        'sales.payment_methods.orange_money.merchant_key' => '',
        'sales.payment_methods.orange_money.webhook_secret' => '',
        'sales.payment_methods.airtel_money.client_id' => '',
        'sales.payment_methods.airtel_money.client_secret' => '',
        'sales.payment_methods.airtel_money.merchant_id' => '',
        'sales.payment_methods.airtel_money.webhook_secret' => '',
    ]);

    config([
        'payment_methods.mvola.active' => true,
        'payment_methods.orange_money.active' => true,
        'payment_methods.airtel_money.active' => true,
    ]);

    $methods = Payment::getSupportedPaymentMethods()['payment_methods'];

    expect(collect($methods)->pluck('method')->all())->toBe([
        'mvola',
        'orange_money',
        'airtel_money',
    ])
        ->and(collect($methods)->pluck('available')->all())->toBe([false, false, false]);
});

it('should verify mobile payment webhook signatures against the exact request body', function (string $paymentClass) {
    $body = '{ "status": "completed" }';
    $normalizedBody = '{"status":"completed"}';
    $payment = Mockery::mock($paymentClass)->makePartial();
    $payment->shouldReceive('getWebhookSecret')->andReturn('test-webhook-secret');

    expect($payment->verifyWebhookSignature(
        $body,
        hash_hmac('sha256', $body, 'test-webhook-secret')
    ))->toBeTrue()
        ->and($payment->verifyWebhookSignature(
            $body,
            hash_hmac('sha256', $normalizedBody, 'test-webhook-secret')
        ))->toBeFalse();
})->with([
    'MVola' => MVolaPayment::class,
    'Orange Money' => OrangeMoneyPayment::class,
    'Airtel Money' => AirtelMoneyPayment::class,
]);

it('should extract cart ids from Orange Money and Airtel Money references', function (string $controllerClass, string $methodName, string $reference) {
    $controller = (new ReflectionClass($controllerClass))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($controllerClass, $methodName);

    expect($method->invoke($controller, $reference))->toBe(1866);
})->with([
    'Orange Money' => [OrangeMoneyController::class, 'extractCartId', 'ORANGE_1866_ABCDEF1234'],
    'Airtel Money' => [AirtelMoneyController::class, 'extractCartId', 'AIRTEL_ABCDEF12_1866'],
]);
