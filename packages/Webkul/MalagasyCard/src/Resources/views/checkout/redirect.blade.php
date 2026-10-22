<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@lang('malagasy_card::app.title')</title>

    <style>
        .card-payment-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .card-payment-logo {
            width: 120px;
            margin: 0 auto 20px;
        }
        .card-payment-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        .card-payment-amount {
            font-size: 28px;
            font-weight: 700;
            color: #6366f1;
            margin: 20px 0;
        }
        .card-payment-info {
            text-align: left;
            background: #f8f9ff;
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
        }
        .card-payment-info ul {
            margin-left: 20px;
            margin-bottom: 0;
        }
        .card-payment-btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .card-payment-btn:hover {
            background: #4f46e5;
        }
        .card-payment-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .card-payment-secure-badge {
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
        .card-payment-secure-badge::before {
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
    <div class="card-payment-container">
        <h2 class="card-payment-title">@lang('malagasy_card::app.title')</h2>

        <p class="card-payment-amount">{{ core()->formatBasePrice($amount) }}</p>

        <div class="card-payment-info">
            <ul>
                <li>@lang('malagasy_card::app.redirect.info-1')</li>
                <li>@lang('malagasy_card::app.redirect.info-2')</li>
                <li>@lang('malagasy_card::app.redirect.info-3')</li>
            </ul>
        </div>

        <form id="card-payment-form" method="GET" action="{{ route('malagasy_card.redirect') }}">
            <button type="submit" class="card-payment-btn" id="card-pay-button">
                @lang('malagasy_card::app.redirect.pay-button')
            </button>
        </form>

        <div class="card-payment-secure-badge">
            @lang('malagasy_card::app.redirect.secure-payment')
        </div>
    </div>

    <script>
        document.getElementById('card-payment-form').addEventListener('submit', function(e) {
            var button = document.getElementById('card-pay-button');
            button.disabled = true;
            button.textContent = '{{ trans("malagasy_card::app.redirect.processing") }}';
        });
    </script>
</body>

</html>
