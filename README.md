<p align="center">
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/dt/msilabs/bkash" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/v/msilabs/bkash" alt="Latest Stable Version"></a>
  <a href="https://github.com/msilabs/bkash/actions/workflows/tests.yml"><img src="https://github.com/msilabs/bkash/actions/workflows/tests.yml/badge.svg?branch=3.x" alt="Tests"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/l/msilabs/bkash" alt="License"></a>
  <a href="https://github.com/msilabs/bkash"><img src="https://img.shields.io/github/stars/msilabs/bkash" alt="GitHub Stars"></a>
</p>

# msilabs/bkash

bKash Tokenized Checkout (v2) for Laravel. Built for one merchant or many.

```php
use Msilabs\Bkash\Facades\Bkash;

$payment = Bkash::createPayment(500, 'INV-1001', route('bkash.callback'));

return redirect($payment->redirectUrl());
```

## Features

- bKash **Tokenized Checkout v2** API
- Create, execute, query and search payments
- Full and partial **refunds**, plus refund status
- **Agreement** (one-click) payments
- **Multiple merchants** from config, or from your database at runtime
- Automatic token caching and refresh, with a lock against concurrent token requests
- A response object with `isCompleted()`, `redirectUrl()`, `errorMessage()` and Bangla error messages
- Optional API logging with secrets masked, for bKash merchant validation
- Built-in sandbox validation routes
- Tested on PHP 8.2–8.5 and Laravel 9–13

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Multiple Merchants](#multiple-merchants)
- [Refund](#refund)
- [Query and Search](#query-and-search)
- [Agreement (One-Click) Payments](#agreement-one-click-payments)
- [Working with Responses](#working-with-responses)
- [Error Handling](#error-handling)
- [Logging](#logging)
- [Sandbox Validation](#sandbox-validation)
- [Going Live](#going-live)
- [Testing Your App](#testing-your-app)
- [API Reference](#api-reference)
- [Upgrading from 2.x](#upgrading-from-2x)

## Requirements

| Package | PHP | Laravel | bKash API |
|---|---|---|---|
| **3.x** | 8.2+ | 9.33+, 10, 11, 12, 13 | Tokenized v2 |
| 2.x | 7.4+ | 7+ | Tokenized v1.2.0-beta |

## Installation

```bash
composer require msilabs/bkash
```

The service provider and the `Bkash` facade register automatically.

To publish the config file (optional):

```bash
php artisan vendor:publish --tag=bkash-config
```

## Configuration

Add your credentials to `.env`:

```dotenv
BKASH_SANDBOX=true
BKASH_APP_KEY=
BKASH_APP_SECRET=
BKASH_USERNAME=
BKASH_PASSWORD=
```

That is all a single merchant needs. Every other setting is optional:

| Env | Default | Description |
|---|---|---|
| `BKASH_ACCOUNT` | `primary` | Default account for `Bkash::...` calls |
| `BKASH_TIMEOUT` | `30` | HTTP timeout in seconds |
| `BKASH_CACHE_STORE` | default store | Cache store for tokens, e.g. `redis` |
| `BKASH_LOG` | `false` | Log every API request and response |
| `BKASH_LOG_CHANNEL` | default channel | Log channel to use |
| `BKASH_SANDBOX_ROUTES` | `false` | Enable the `/bkash-sandbox/*` routes |
| `BKASH_SANDBOX_URL` | `https://tokenized.sandbox.bka.sh/v2` | Sandbox base URL |
| `BKASH_PRODUCTION_URL` | `https://tokenized.pay.bka.sh/v2` | Production base URL |

> If bKash gives you a different base URL during onboarding, set it in `BKASH_PRODUCTION_URL`, or as `base_url` on a single account.

## Quick Start

A payment has three steps:

1. **Create** the payment and redirect the customer to bKash.
2. bKash redirects the customer back to your **callback URL** with `status` and `paymentId`.
3. **Execute** the payment on success. The money is taken only at this step.

### Routes

```php
// routes/web.php
use App\Http\Controllers\BkashController;

Route::post('/bkash/pay', [BkashController::class, 'pay'])->name('bkash.pay');
Route::get('/bkash/callback', [BkashController::class, 'callback'])->name('bkash.callback');
```

### Controller

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Msilabs\Bkash\Facades\Bkash;

class BkashController extends Controller
{
    public function pay(Request $request)
    {
        $order = Order::findOrFail($request->order_id);

        $payment = Bkash::createPayment(
            amount: $order->total,
            invoiceNumber: $order->invoice_no,
            callbackUrl: route('bkash.callback'),
        );

        if ($payment->failed()) {
            return back()->with('error', $payment->errorMessage());
        }

        $order->update(['bkash_payment_id' => $payment->paymentId]);

        return redirect($payment->redirectUrl());
    }

    public function callback(Request $request)
    {
        $paymentId = $request->query('paymentId', $request->query('paymentID'));

        $order = Order::where('bkash_payment_id', $paymentId)->firstOrFail();

        if ($request->query('status') !== 'success') {
            // "cancel" or "failure"
            return redirect()->route('checkout')->with('error', 'Payment was not completed.');
        }

        $result = Bkash::executePayment($paymentId);

        // If execute timed out or failed, ask bKash for the real status.
        if (! $result->isCompleted()) {
            $result = Bkash::queryPayment($paymentId);
        }

        $paidAmount = number_format((float) $result->amount, 2, '.', '');
        $orderAmount = number_format((float) $order->total, 2, '.', '');

        if ($result->isCompleted() && $paidAmount === $orderAmount) {
            $order->update([
                'status' => 'paid',
                'bkash_trx_id' => $result->trxId,
            ]);

            return redirect()->route('orders.show', $order)->with('success', 'Payment successful.');
        }

        return redirect()->route('checkout')->with('error', $result->errorMessage() ?? 'Payment failed.');
    }
}
```

> **Store the `paymentId`** when you create the payment, and look the order up by it in the callback. Never trust the amount or invoice number from the callback URL.

### createPayment options

```php
Bkash::createPayment(
    amount: 500,                     // number or string, sent as "500.00"
    invoiceNumber: 'INV-1001',       // your unique invoice/order ID
    callbackUrl: route('bkash.callback'),
    payerReference: '01712345678',   // optional, prefills the wallet number on the bKash page
    extra: ['subMerchantName' => 'My Shop'], // optional extra fields (aggregators)
);
```

## Multiple Merchants

### Option 1: Accounts in config

Publish the config, then add one entry per merchant:

```php
// config/bkash.php
'accounts' => [
    'primary' => [
        'sandbox' => env('BKASH_SANDBOX', true),
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
    ],

    'donation' => [
        'sandbox' => env('BKASH_DONATION_SANDBOX', true),
        'app_key' => env('BKASH_DONATION_APP_KEY'),
        'app_secret' => env('BKASH_DONATION_APP_SECRET'),
        'username' => env('BKASH_DONATION_USERNAME'),
        'password' => env('BKASH_DONATION_PASSWORD'),
    ],
],
```

Pick the account with `account()`:

```php
Bkash::createPayment(...);                       // default account ("primary")
Bkash::account('donation')->createPayment(...);  // donation merchant
Bkash::account('donation')->executePayment($paymentId);
```

Use the same account for create, execute, query and refund of one payment. Each account has its own token cache.

To change the default account, set `BKASH_ACCOUNT=donation`.

### Option 2: Credentials from your database

For SaaS or marketplace apps where each shop has its own bKash merchant:

```php
$shop = Shop::findOrFail($shopId);

$bkash = Bkash::using([
    'sandbox' => $shop->bkash_sandbox,
    'app_key' => $shop->bkash_app_key,
    'app_secret' => $shop->bkash_app_secret,
    'username' => $shop->bkash_username,
    'password' => $shop->bkash_password,
], name: "shop-{$shop->id}");

$payment = $bkash->createPayment($order->total, $order->invoice_no, route('bkash.callback', $shop));
```

Tokens are cached per credential set, so each shop gets its own token. `sandbox` defaults to `true` when you leave it out.

> Store these credentials encrypted, for example with Laravel's `encrypted` cast.

## Refund

Refund all or part of a completed payment:

```php
$refund = Bkash::refund(
    paymentId: $order->bkash_payment_id,
    trxId: $order->bkash_trx_id,
    amount: 200,
    reason: 'Product out of stock',
    sku: 'SKU-123', // optional
);

if ($refund->isCompleted()) {
    $refundTrxId = $refund->refundTrxId;
}
```

Check refunds already made for a payment:

```php
$status = Bkash::refundStatus($paymentId, $trxId);

foreach ($status->refundTransactions ?? [] as $item) {
    // $item['refundTrxId'], $item['refundAmount'], $item['refundTransactionStatus']
}
```

bKash refund rules:

- Up to 10 partial refunds per transaction, up to the `maxRefundableAmount`.
- Only within 60 days of the payment.
- The same refund can't be repeated within 10 minutes.
- If the refund call times out, call `refundStatus()` before retrying, so you don't refund twice.

```php
use Msilabs\Bkash\Exceptions\BkashException;

try {
    $refund = Bkash::refund($paymentId, $trxId, 200, 'Customer request');
} catch (BkashException $e) {
    // Timeout or connection error: check before retrying.
    $refund = Bkash::refundStatus($paymentId, $trxId);
}
```

## Query and Search

```php
// By payment ID: status of a payment you created
$payment = Bkash::queryPayment($paymentId);
$payment->transactionStatus;   // "Initiated", "Completed", ...
$payment->maxRefundableAmount;

// By transaction ID: details of a completed transaction
$trx = Bkash::searchTransaction($trxId);
$trx->amount;
$trx->payerAccount;
```

Use `queryPayment()` to reconcile orders left pending when a customer closed the browser before the callback.

## Agreement (One-Click) Payments

With an agreement, a returning customer pays with only their PIN, with no OTP.

**1. Create the agreement** (once per customer):

```php
$agreement = Bkash::createAgreement(
    payerReference: $user->phone,
    callbackUrl: route('bkash.agreement.callback'),
);

return redirect($agreement->redirectUrl());
```

**2. Execute it in the callback** and save the `agreementId`:

```php
if ($request->query('status') === 'success') {
    $result = Bkash::executeAgreement($request->query('agreementId'));

    if ($result->isCompleted()) {
        $user->update(['bkash_agreement_id' => $result->agreementId]);
    }
}
```

**3. Pay with the agreement:**

```php
$payment = Bkash::createPaymentWithAgreement(
    agreementId: $user->bkash_agreement_id,
    amount: 300,
    invoiceNumber: 'INV-1002',
    callbackUrl: route('bkash.callback'),
);

return redirect($payment->redirectUrl());

// In the callback:
$result = Bkash::executePaymentWithAgreement($paymentId, $user->bkash_agreement_id);
```

**Other agreement calls:**

```php
Bkash::queryAgreement($agreementId);
Bkash::cancelAgreement($agreementId);  // e.g. when the user unlinks bKash
```

## Working with Responses

Every API method returns a `Msilabs\Bkash\BkashResponse`:

```php
$response = Bkash::executePayment($paymentId);

$response->successful();     // true if bKash returned no error
$response->failed();         // opposite of successful()
$response->isCompleted();    // transaction, agreement or refund status is "Completed"
$response->redirectUrl();    // bKash page URL (create payment / agreement)
$response->invoiceNumber();  // your invoice number (execute, query, ...)

$response->errorCode();      // e.g. "2023"
$response->errorMessage();   // e.g. "Insufficient Balance"
$response->errorMessageBn(); // Bangla message, if bKash sent one

$response->trxId;            // property access
$response['trxId'];          // array access
$response->get('refundTransactions.0.refundTrxId'); // dot notation
$response->toArray();        // raw bKash response
$response->status();         // HTTP status code
```

It is also JSON serializable, so you can `return $response;` from a controller or store `$response->toArray()` in a JSON column.

## Error Handling

Errors come in two kinds:

| Situation | What you get |
|---|---|
| bKash rejected the request (wrong PIN, insufficient balance, invalid payment ID, ...) | A response with `failed() === true`. No exception. |
| Missing or wrong credentials, token failure, timeout, connection error, non-JSON reply | `Msilabs\Bkash\Exceptions\BkashException` |
| Unknown account name | `InvalidArgumentException` |

```php
use Msilabs\Bkash\Exceptions\BkashException;

try {
    $response = Bkash::executePayment($paymentId);

    if ($response->failed()) {
        logger()->warning('bKash payment failed', $response->toArray());

        return back()->with('error', $response->errorMessage());
    }
} catch (BkashException $e) {
    report($e);

    return back()->with('error', 'bKash is unavailable. Please try again.');
}
```

Prefer exceptions everywhere? Chain `throw()`:

```php
$response = Bkash::executePayment($paymentId)->throw();
```

`BkashException` exposes `$e->errorCode` and `$e->response` (a `BkashResponse` when available).

Show `errorMessage()` to customers, not the error code. bKash's full error code list is in the [bKash developer docs](https://developer.bka.sh/docs/error-codes).

## Logging

bKash often asks for API logs during merchant validation. To log every request and response:

```dotenv
BKASH_LOG=true
BKASH_LOG_CHANNEL=bkash   # optional
```

`app_secret`, `username`, `password`, `Authorization`, `id_token` and `refresh_token` are replaced with `********`. Customer wallet numbers (`customerMsisdn`, `payerAccount`) keep only their last 4 digits.

An example dedicated channel in `config/logging.php`:

```php
'bkash' => [
    'driver' => 'daily',
    'path' => storage_path('logs/bkash.log'),
    'days' => 30,
],
```

## Sandbox Validation

The package includes routes to test your sandbox credentials without writing any code.

```dotenv
BKASH_SANDBOX=true
BKASH_SANDBOX_ROUTES=true
```

```bash
php artisan serve
```

| URL | What it does |
|---|---|
| `/bkash-sandbox/create` | Creates a 10 BDT payment and redirects to bKash |
| `/bkash-sandbox/callback` | bKash returns here. Executes the payment and shows the JSON result |
| `/bkash-sandbox/query/{paymentId}` | Shows the query payment result |

For another account, add its name at the end: `/bkash-sandbox/create/donation`.

The routes respond only for accounts with `sandbox` set to `true`, and return 404 otherwise.

> Turn `BKASH_SANDBOX_ROUTES` off in production.

## Going Live

- [ ] Set `BKASH_SANDBOX=false` and your live credentials.
- [ ] Remove `BKASH_SANDBOX_ROUTES`, or set it to `false`.
- [ ] Use a shared cache store (Redis, database) if you run more than one server, so all servers share one token.
- [ ] Execute payments only on the server, after a `status=success` callback.
- [ ] Find the order by the `paymentId` you stored, and check that the executed `amount` matches the order.
- [ ] Make your callback idempotent. A customer may reload it, and a payment can be executed only once.
- [ ] Reconcile pending orders with `queryPayment()`, for example from a scheduled job.
- [ ] Run `php artisan config:cache`.

## Testing Your App

The package uses Laravel's HTTP client, so you can fake bKash in your own tests:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/tokenized-checkout/auth/grant-token' => Http::response([
        'statusCode' => '0000',
        'id_token' => 'fake-token',
        'refresh_token' => 'fake-refresh',
        'expires_in' => 3600,
    ]),
    '*/tokenized-checkout/payment/create' => Http::response([
        'paymentId' => 'TR0011TEST',
        'bkashURL' => 'https://sandbox.payment.bkash.com/?paymentId=TR0011TEST',
        'transactionStatus' => 'Initiated',
    ]),
    '*/tokenized-checkout/payment/execute' => Http::response([
        'paymentId' => 'TR0011TEST',
        'trxId' => 'TRX123',
        'transactionStatus' => 'Completed',
        'amount' => '500.00',
    ]),
]);

$this->post('/bkash/pay', ['order_id' => $order->id])
    ->assertRedirect('https://sandbox.payment.bkash.com/?paymentId=TR0011TEST');
```

## API Reference

All methods work on the facade (default account), on `Bkash::account('name')` and on `Bkash::using([...])`.

| Method | bKash API |
|---|---|
| `createPayment($amount, $invoiceNumber, $callbackUrl, $payerReference = null, $extra = [])` | Create Payment |
| `executePayment($paymentId)` | Execute Payment |
| `queryPayment($paymentId)` | Query Payment |
| `searchTransaction($trxId)` | Search Transaction |
| `refund($paymentId, $trxId, $amount, $reason, $sku = 'N/A')` | Refund |
| `refundStatus($paymentId, $trxId)` | Refund Status |
| `createAgreement($payerReference, $callbackUrl)` | Create Agreement |
| `executeAgreement($agreementId)` | Execute Agreement |
| `queryAgreement($agreementId)` | Query Agreement |
| `cancelAgreement($agreementId)` | Cancel Agreement |
| `createPaymentWithAgreement($agreementId, $amount, $invoiceNumber, $callbackUrl, $extra = [])` | Create Payment with Agreement |
| `executePaymentWithAgreement($paymentId, $agreementId)` | Execute Payment with Agreement |
| `token($fresh = false)` | Grant / Refresh Token (cached) |
| `forgetToken()` | Clears the cached token |
| `isSandbox()`, `baseUrl()`, `name()` | Account info |

Manager methods: `account($name = null)`, `using(array $credentials, $name = 'custom')`, `accounts()`, `getDefaultAccount()`, `purge($name = null)`.

## Upgrading from 2.x

3.0 uses the bKash v2 API and a facade instead of the trait. See [UPGRADE.md](UPGRADE.md) for the step-by-step guide.

## Contributing

```bash
composer install
composer test     # PHPUnit
composer format   # Laravel Pint
```

Pull requests are welcome. Please add tests for new features.

## Security

If you find a security issue, email msi313.bd@gmail.com instead of opening a public issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See [LICENSE](LICENSE).
