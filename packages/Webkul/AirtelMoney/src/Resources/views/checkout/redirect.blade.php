@php
    $customerPhone = $customerPhone ?? '';
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@lang('airtel_money::app.title')</title>

    <style>
        .airtel-money-payment-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .airtel-money-logo {
            width: 120px;
            margin: 0 auto 20px;
        }
        .airtel-money-payment-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        .airtel-money-payment-amount {
            font-size: 28px;
            font-weight: 700;
            color: #e60000;
            margin: 20px 0;
        }
        .airtel-money-instructions {
            text-align: left;
            background: #fff5f5;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
        }
        .airtel-money-instructions ol {
            margin-left: 20px;
            margin-bottom: 0;
        }
        .airtel-money-payment-btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            background: #e60000;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .airtel-money-payment-btn:hover {
            background: #c70000;
        }
        .airtel-money-payment-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .airtel-money-phone-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin: 15px 0;
            text-align: center;
        }
        .airtel-money-secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            color: #166534;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }
        .airtel-money-secure-badge::before {
            content: "✓";
            display: inline-block;
            width: 18px;
            height: 18px;
            background: #22c55e;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 18px;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="airtel-money-payment-container">
        <h2 class="airtel-money-payment-title">@lang('airtel_money::app.title')</h2>

        <p class="airtel-money-payment-amount">{{ core()->formatBasePrice($amount) }}</p>

        <div class="airtel-money-instructions">
            <ol>
                <li>@lang('airtel_money::app.redirect.step-1')</li>
                <li>@lang('airtel_money::app.redirect.step-2')</li>
                <li>@lang('airtel_money::app.redirect.step-3')</li>
            </ol>
        </div>

        @if ($paymentStarted)
            <p role="status" class="mb-4 text-sm">@lang('airtel_money::app.response.payment-pending')</p>

            @if ($transactionReference)
                <p class="mb-4 text-xs text-gray-500">{{ $transactionReference }}</p>
            @endif

            <a href="{{ route('shop.checkout.cart.index') }}" class="airtel-money-payment-btn">
                @lang('airtel_money::app.redirect.click-if-not-redirected')
            </a>
        @else
            <form id="airtel-money-payment-form" method="POST" action="{{ route('airtel_money.pay') }}">
                @csrf
                <input type="tel" name="customer_phone" class="airtel-money-phone-input" placeholder="@lang('airtel_money::app.redirect.phone-placeholder')" value="{{ $customerPhone }}" required>
                @error('customer_phone')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                <button type="submit" class="airtel-money-payment-btn" id="airtel-money-pay-button">
                    @lang('airtel_money::app.redirect.pay-button')
                </button>
            </form>
        @endif

        <div class="airtel-money-secure-badge">
            @lang('airtel_money::app.redirect.secure-payment')
        </div>
    </div>

    <script>
        document.getElementById('airtel-money-payment-form')?.addEventListener('submit', function() {
            var button = document.getElementById('airtel-money-pay-button');
            button.disabled = true;
            button.textContent = '{{ trans("airtel_money::app.redirect.processing") }}';
        });
    </script>
</body>

</html>
