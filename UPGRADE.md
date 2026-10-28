# Upgrade Guide

## Upgrading from 2.x to 3.0

Estimated time: 15–30 minutes for a typical create/execute integration.

3.0 moves to the bKash **Tokenized Checkout v2** API and replaces the `BkashPayment` trait with the `Bkash` facade. The v2 API renamed several response fields, so check every place that reads a bKash response.

### 1. Requirements

| | 2.x | 3.x |
|---|---|---|
| PHP | 7.4+ | **8.2+** |
| Laravel | 7+ | **9.33+** |
| bKash API | v1.2.0-beta | **v2** |

### 2. Update Composer

```bash
composer require msilabs/bkash:^3.0
```

### 3. Republish the config

The config file has new keys. If you published it before, back up your `accounts` section, then republish:

```bash
php artisan vendor:publish --tag=bkash-config --force
```

Copy your extra accounts back into the new `accounts` array. The account keys (`sandbox`, `app_key`, `app_secret`, `username`, `password`) are unchanged, so your `.env` keeps working.

Removed keys: `base_url` and `sandbox_url`. They are now `urls.production` and `urls.sandbox`, and both point to v2.

### 4. Replace the trait with the facade

Before (2.x):

```php
use Msilabs\Bkash\BkashPayment;

class PaymentController extends Controller
{
    use BkashPayment;

    public function pay()
    {
        $response = $this->createPayment($amount, $invoice, $callbackUrl);

        return redirect($response->bkashURL);
    }
}
```

After (3.x):

```php
use Msilabs\Bkash\Facades\Bkash;

class PaymentController extends Controller
{
    public function pay()
    {
        $response = Bkash::createPayment($amount, $invoice, $callbackUrl);

        return redirect($response->redirectUrl());
    }
}
```

| 2.x | 3.x |
|---|---|
| `use BkashPayment;` | `use Msilabs\Bkash\Facades\Bkash;` |
| `$this->createPayment(...)` | `Bkash::createPayment(...)` |
| `$this->executePayment($id)` | `Bkash::executePayment($id)` |
| `$this->queryPayment($id)` | `Bkash::queryPayment($id)` |
| `$this->getToken()` | `Bkash::token()` |
| `$this->getToken(false)` | `Bkash::token(fresh: true)` |
| `$this->setAccount('secondary'); $this->createPayment(...)` | `Bkash::account('secondary')->createPayment(...)` |

### 5. Response field names (v2)

| 2.x (v1.2.0-beta) | 3.x (v2) |
|---|---|
| `$response->paymentID` | `$response->paymentId` |
| `$response->bkashURL` | `$response->redirectUrl()` |
| `$response->trxID` | `$response->trxId` |
| `$response->transactionStatus == 'Completed'` | `$response->isCompleted()` |
| `$response['merchantInvoiceNumber']` | `$response->invoiceNumber()` (Query Payment returns it as `merchantInvoice`) |

Callback query parameters: read the payment ID with a fallback, since the casing can differ:

```php
$paymentId = $request->query('paymentId', $request->query('paymentID'));
```

Responses are now `Msilabs\Bkash\BkashResponse` objects, not `stdClass`. Property access (`$response->paymentId`) and array access (`$response['paymentId']`) both work. Use `$response->toArray()` for a plain array.

### 6. Error handling

2.x threw `ErrorException` for everything. 3.x splits errors in two:

- **bKash rejected the request** (wrong PIN, insufficient balance, invalid payment ID): no exception. You get a response where `$response->failed()` is `true`, with `errorCode()` and `errorMessage()`.
- **Bad credentials or no connection** (token failure, timeout, invalid JSON): throws `Msilabs\Bkash\Exceptions\BkashException`.

Before:

```php
try {
    $response = $this->executePayment($paymentId);
} catch (\ErrorException $e) {
    // ...
}
```

After:

```php
use Msilabs\Bkash\Exceptions\BkashException;

try {
    $response = Bkash::executePayment($paymentId);

    if ($response->isCompleted()) {
        // paid
    } else {
        $message = $response->errorMessage();
    }
} catch (BkashException $e) {
    // network or credential problem
}
```

To keep the old "always throw" style, call `->throw()`:

```php
$response = Bkash::executePayment($paymentId)->throw();
```

### 7. Sandbox validation routes

Routes are now **off by default** and use a new path.

```dotenv
BKASH_SANDBOX_ROUTES=true
```

| 2.x | 3.x |
|---|---|
| `/bkash-sandbox-validation/create-payment/{account?}` | `/bkash-sandbox/create/{account?}` |
| `/bkash-sandbox-validation/execute-payment/{account?}` | `/bkash-sandbox/callback/{account?}` (bKash redirects here) |
| — | `/bkash-sandbox/query/{paymentId}/{account?}` |

The routes return 404 for accounts with `sandbox` set to `false`.

### 8. Other changes

- An unknown account name now throws `InvalidArgumentException`. 2.x silently used `primary`.
- `payerReference` defaults to the invoice number. Pass the customer's wallet number as the 4th argument to prefill it on the bKash page.
- Token cache keys changed. Old cached tokens are ignored, so the first request after deploying fetches a new token.
- The static account state from 2.x is gone, so a selected account no longer leaks between requests on Octane or in queues.

### 9. Test in sandbox before going live

1. Set `BKASH_SANDBOX=true` and `BKASH_SANDBOX_ROUTES=true`.
2. Visit `/bkash-sandbox/create`, pay with a bKash sandbox wallet, and check that the callback shows `"transactionStatus": "Completed"`.
3. Run your own checkout flow end to end.
4. Set `BKASH_SANDBOX=false`, remove `BKASH_SANDBOX_ROUTES`, and switch to your live credentials.
