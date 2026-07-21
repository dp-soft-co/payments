# Dpsoft Payment Gateways

[![Latest Version](https://img.shields.io/github/v/tag/dp-soft-co/payments)](https://github.com/dp-soft-co/payments/tags)
[![MIT License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)
[![Packagist](https://img.shields.io/badge/Composer-dp--soft--co/payments-blue)](https://packagist.org/packages/dp-soft-co/payments)

Payment Helper for Laravel — supports PayPal, Paymob, Kashier, Fawry, HyperPay, Thawani, Tap, Opay, Paytabs, Binance, PerfectMoney, NowPayments, Payeer, Telr, Clickpay, MyFatoorah, Stripe, and E-Wallets (Vodafone Cash, Orange Money, Meza Wallet, Etisalat Cash).

![payment-gateways.jpg](https://github.com/dp-soft-co/payments/blob/master/payment-gateways.jpg?raw=true&v=6)

> **Forked from [nafezly/payments](https://github.com/nafezly/payments)** with Kashier Payment Sessions API fix and improvements.


## Supported gateways

- [PayPal](https://paypal.com/)
- [PayMob](https://paymob.com/)
- [WeAccept](https://paymob.com/)
- [Kashier](https://kashier.io/)
- [Fawry](https://fawry.com/)
- [HyperPay](https://www.hyperpay.com/)
- [Thawani](https://thawani.om/)
- [Tap](https://www.tap.company/)
- [Opay](https://www.opaycheckout.com/)
- [Paytabs](https://site.paytabs.com/)
- [Binance](https://www.binance.com/en)
- [PerfectMoney](https://PerfectMoney.com/)
- [NowPayments](https://NowPayments.io/)
- [Payeer](https://payeer.com)
- [Telr](https://telr.com)
- [Clickpay](https://clickpay.com.sa/)
- [MyFatoorah](https://myfatoorah.com/)
- [Stripe](https://stripe.com/)
- [E Wallets (Vodafone Cash - Orange Money - Meza Wallet - Etisalat Cash)](https://paymob.com/)

## Installation

```bash
composer require dp-soft-co/payments
```

## Publish Vendor Files

```jsx
php artisan vendor:publish --tag="dpsoft-payments-config"
php artisan vendor:publish --tag="dpsoft-payments-lang"
```

### dpsoft-payments.php file

```php
<?php
return [

    #PAYMOB
    'PAYMOB_SECRET_KEY' => env('PAYMOB_SECRET_KEY'),
    'PAYMOB_PUBLIC_KEY' => env('PAYMOB_PUBLIC_KEY'),
    'PAYMOB_BASE_URL' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
    'PAYMOB_INTEGRATION_ID' => env('PAYMOB_INTEGRATION_ID'),
    'PAYMOB_HMAC' => env('PAYMOB_HMAC'),
    'PAYMOB_CURRENCY'=> env('PAYMOB_CURRENCY',"EGP"),
    'PAYMOB_NOTIFICATION_URL' => env('PAYMOB_NOTIFICATION_URL'),
    'PAYMOB_REDIRECTION_URL' => env('PAYMOB_REDIRECTION_URL'),


    #HYPERPAY
    'HYPERPAY_BASE_URL' => env('HYPERPAY_BASE_URL', "https://eu-test.oppwa.com"),
    'HYPERPAY_URL' => env('HYPERPAY_URL', env('HYPERPAY_BASE_URL') . "/v1/checkouts"),
    'HYPERPAY_TOKEN' => env('HYPERPAY_TOKEN'),
    'HYPERPAY_CREDIT_ID' => env('HYPERPAY_CREDIT_ID'),
    'HYPERPAY_MADA_ID' => env('HYPERPAY_MADA_ID'),
    'HYPERPAY_APPLE_ID' => env('HYPERPAY_APPLE_ID'),
    'HYPERPAY_CURRENCY' => env('HYPERPAY_CURRENCY', "SAR"),


    #KASHIER
    'KASHIER_ACCOUNT_KEY' => env('KASHIER_ACCOUNT_KEY'),
    'KASHIER_IFRAME_KEY' => env('KASHIER_IFRAME_KEY'),
    'KASHIER_TOKEN' => env('KASHIER_TOKEN'),
    'KASHIER_URL' => env('KASHIER_URL', "https://checkout.kashier.io"),
    'KASHIER_MODE' => env('KASHIER_MODE', "test"), //live or test
    'KASHIER_CURRENCY'=>env('KASHIER_CURRENCY',"EGP"),
    'KASHIER_WEBHOOK_URL'=>env('KASHIER_WEBHOOK_URL'),


    #STRIPE
    'STRIPE_SECRET_KEY' => env('STRIPE_SECRET_KEY'),
    'STRIPE_PUBLISHABLE_KEY' => env('STRIPE_PUBLISHABLE_KEY'),
    'STRIPE_CURRENCY' => env('STRIPE_CURRENCY', 'USD'),


    'VERIFY_ROUTE_NAME' => "verify-payment",
    'APP_NAME'=>env('APP_NAME'),
    //and more config for another payment gateways
];
```

## How To Use

### Standard Controller Example

The following Laravel controller pattern works with every gateway supported by the package. Store the gateway class name used to create the invoice so the callback can use the same gateway. Replace `Invoice`, the payment view, and success/failure routes with your application equivalents.

```php
use App\Models\Invoice;
use Dpsoft\Payments\Facades\DpsoftPaymentsFacade as DpsoftPayments;
use Illuminate\Http\Request;

public function pay(Request $request)
{
    $data = $request->validate([
        'gateway' => ['required', 'string'],
        'amount' => ['required', 'numeric', 'min:0.01'],
    ]);

    $user = $request->user();
    $gateway = $data['gateway'];
    $source = match (strtolower($gateway)) {
        'kashier' => 'card,bank_installments,wallet,fawry',
        'hyperpay' => 'CREDIT',
        default => null,
    };

    $response = DpsoftPayments::gateway($gateway)
        ->setPaymentData([
            'amount' => $data['amount'],
            'user_id' => $user->id,
            'user_first_name' => $user->firstname,
            'user_last_name' => $user->lastname,
            'user_email' => $user->email,
            'user_phone' => $user->phone,
            'source' => $source,
        ])
        ->pay();

    if (empty($response['payment_id'])) {
        abort(422, strip_tags($response['html'] ?? 'Payment creation failed.'));
    }

    Invoice::create([
        'user_id' => $user->id,
        'amount' => $data['amount'],
        'status' => 'pending',
        'pid' => $response['payment_id'],
        'gateway' => $gateway,
    ]);

    if (!empty($response['redirect_url'])) {
        return redirect()->away($response['redirect_url']);
    }

    if (!empty($response['html'])) {
        return view('payments.pay', ['link' => $response['html']]);
    }

    abort(422, 'Invalid gateway response.');
}

public function verify(string $gateway, Request $request)
{
    $result = DpsoftPayments::gateway($gateway)->verify($request);

    $invoice = Invoice::where('pid', $result['payment_id'] ?? null)
        ->where('gateway', $gateway)
        ->firstOrFail();

    if ($result['success']) {
        $invoice->update(['status' => 'paid']);

        return redirect()->route('payments.success');
    }

    return redirect()->route('payments.failed');
}
```

Render gateway HTML only when it comes directly from the package response:

```blade
{!! $link !!}
```

Register the verification route:

```php
Route::match(['get', 'post'], '/payments/verify/{gateway}', [PaymentController::class, 'verify'])
    ->name('verify-payment');
```

### CSRF Protection

Payment gateways send POST callbacks from an iframe or external page and will not include the Laravel CSRF token. You must exclude the verification route from CSRF validation:

- **Laravel 11** (`bootstrap/app.php`):
  ```php
  $middleware->validateCsrfTokens(except: [
      '*payment/verify*',
  ]);
  ```

- **Laravel 10** (`app/Http/Middleware/VerifyCsrfToken.php`):
  ```php
  protected $except = [
      '*payment/verify*',
  ];
  ```

Adjust the wildcard to match your actual route path and any language prefix (e.g. `*payments/verify*`).

Use the gateway class name when starting a payment, for example `Paymob`, `PaymobWallet`, `Kashier`, `Fawry`, or `MyFatoorah`.

#### Gateway-specific `source` values

The standard example assigns a fixed `source` value from the selected gateway. Change the values in the `match` expression to match the payment methods enabled in your merchant account.

| Gateway | `source` values |
| --- | --- |
| `Kashier` | Comma-separated allowed methods, such as `card,wallet`, `card,bank_installments,wallet,fawry`, or another supported Kashier method list. If omitted, the gateway defaults to `card,wallet`. |
| `HyperPay` | `CREDIT`, `MADA`, or `APPLE`. This is required to select the matching HyperPay entity ID and payment brand. |

Most other gateways do not use `source`; passing `null` is valid.

## Some Test Cards

- [Thawani](https://docs.thawani.om/docs/thawani-ecommerce-api/ZG9jOjEyMTU2Mjc3-thawani-test-card)
- [Kashier](https://developers.kashier.io/payment/testing)
- [Paymob](https://docs.paymob.com/docs/card-payments)
- [Fawry](https://developer.fawrystaging.com/docs/testing/testing)
- [Tap](https://www.tap.company/eg/en/developers)
- [Opay](https://doc.opaycheckout.com/end-to-end-testing)
- [PayTabs](https://support.paytabs.com/en/support/solutions/articles/60000712315-what-are-the-test-cards-available-to-perform-payments-)

