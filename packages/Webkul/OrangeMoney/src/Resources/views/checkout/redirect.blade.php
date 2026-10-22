@php
    $customerPhone = $customerPhone ?? '';
@endphp

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@lang('orange_money::app.title')</title>

    <style>
        .orange-money-payment-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .orange-money-logo {
            width: 120px;
            margin: 0 auto 20px;
        }
        .orange-money-payment-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        .orange-money-payment-amount {
            font-size: 28px;
            font-weight: 700;
            color: #ff6b00;
            margin: 20px 0;
        }
        .orange-money-instructions {
            text-align: left;
            background: #fff7f0;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
        }
        .orange-money-instructions ol {
            margin-left: 20px;
            margin-bottom: 0;
        }
        .orange-money-payment-btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            background: #ff6b00;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .orange-money-payment-btn:hover {
            background: #e55a00;
        }
        .orange-money-payment-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .orange-money-phone-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin: 15px 0;
            text-align: center;
        }
        .orange-money-secure-badge {
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
        .orange-money-secure-badge::before {
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
    <div class="orange-money-payment-container">
        <h2 class="orange-money-payment-title">@lang('orange_money::app.title')</h2>

        <p class="orange-money-payment-amount">{{ core()->formatBasePrice($amount) }}</p>

        <div class="orange-money-instructions">
            <ol>
                <li>@lang('orange_money::app.redirect.step-1')</li>
                <li>@lang('orange_money::app.redirect.step-2')</li>
                <li>@lang('orange_money::app.redirect.step-3')</li>
            </ol>
        </div>

        @if ($paymentStarted)
            <p role="status" class="mb-4 text-sm">@lang('orange_money::app.response.payment-pending')</p>

            @if ($transactionReference)
                <p class="mb-4 text-xs text-gray-500">{{ $transactionReference }}</p>
            @endif

            <a href="{{ route('shop.checkout.cart.index') }}" class="orange-money-payment-btn">
                @lang('orange_money::app.redirect.click-if-not-redirected')
            </a>
        @else
            <form id="orange-money-payment-form" method="POST" action="{{ route('orange_money.pay') }}">
                @csrf
                <input type="tel" name="customer_phone" class="orange-money-phone-input" placeholder="@lang('orange_money::app.redirect.phone-placeholder')" value="{{ $customerPhone }}" required>
                @error('customer_phone')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                <button type="submit" class="orange-money-payment-btn" id="orange-money-pay-button">
                    @lang('orange_money::app.redirect.pay-button')
                </button>
            </form>
        @endif

        <div class="orange-money-secure-badge">
            @lang('orange_money::app.redirect.secure-payment')
        </div>
    </div>

    <script>
        document.getElementById('orange-money-payment-form')?.addEventListener('submit', function() {
            var button = document.getElementById('orange-money-pay-button');
            button.disabled = true;
            button.textContent = '{{ trans("orange_money::app.redirect.processing") }}';
        });
    </script>
</body>

</html>
