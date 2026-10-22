<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@lang('mvola::app.title')</title>

    <style>
        .mvola-payment-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .mvola-logo {
            width: 120px;
            margin: 0 auto 20px;
        }
        .mvola-payment-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        .mvola-payment-amount {
            font-size: 28px;
            font-weight: 700;
            color: #0070f3;
            margin: 20px 0;
        }
        .mvola-instructions {
            text-align: left;
            background: #f8f9ff;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
        }
        .mvola-instructions ol {
            margin-left: 20px;
            margin-bottom: 0;
        }
        .mvola-payment-btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            background: #0070f3;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .mvola-payment-btn:hover {
            background: #0055cc;
        }
        .mvola-payment-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .mvola-phone-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            margin: 15px 0;
            text-align: center;
        }
        .mvola-secure-badge {
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
        .mvola-secure-badge::before {
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
    <div class="mvola-payment-container">
        <h2 class="mvola-payment-title">@lang('mvola::app.title')</h2>

        <p class="mvola-payment-amount">{{ core()->formatBasePrice($amount) }}</p>

        <div class="mvola-instructions">
            <ol>
                <li>@lang('mvola::app.redirect.step-1')</li>
                <li>@lang('mvola::app.redirect.step-2')</li>
                <li>@lang('mvola::app.redirect.step-3')</li>
            </ol>
        </div>

        @if ($paymentStarted)
            <p role="status" class="mb-4 text-sm">@lang('mvola::app.response.payment-pending')</p>

            @if ($transactionReference)
                <p class="mb-4 text-xs text-gray-500">{{ $transactionReference }}</p>
            @endif

            <a href="{{ route('shop.checkout.cart.index') }}" class="mvola-payment-btn">
                @lang('mvola::app.redirect.click-if-not-redirected')
            </a>
        @else
            <form id="mvola-payment-form" method="POST" action="{{ route('mvola.pay') }}">
                @csrf
                <input type="tel" name="customer_phone" class="mvola-phone-input" placeholder="@lang('mvola::app.redirect.phone-placeholder')" value="{{ $customerPhone }}" required>
                @error('customer_phone')
                    <p class="text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror
                <button type="submit" class="mvola-payment-btn" id="mvola-pay-button">
                    @lang('mvola::app.redirect.pay-button')
                </button>
            </form>
        @endif

        <div class="mvola-secure-badge">
            @lang('mvola::app.redirect.secure-payment')
        </div>
    </div>

    <script>
        document.getElementById('mvola-payment-form')?.addEventListener('submit', function() {
            var button = document.getElementById('mvola-pay-button');
            button.disabled = true;
            button.textContent = '{{ trans("mvola::app.redirect.processing") }}';
        });
    </script>
</body>

</html>
