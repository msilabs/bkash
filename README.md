<p align="center">
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/dt/msilabs/bkash" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/v/msilabs/bkash" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/l/msilabs/bkash" alt="License"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/github/stars/msilabs/bkash" alt="Github Stars"></a>
</p>

# msilabs/bkash

bKash Payment Gateway API for Laravel Framework.

## Requirements

- PHP >=7.4
- Laravel 7 - 10

## Get Started

Install `msilabs/bkash` via the [Composer](https://getcomposer.org/) package manager:

```bash
composer require msilabs/bkash
```

Optionally, publish the configuration file

```bash
php artisan vendor:publish --provider="Msilabs\Bkash\BkashServiceProvider"
```

Set .env configuration

```bash
BKASH_SANDBOX=true  #for production use false
BKASH_APP_KEY=""
BKASH_APP_SECRET=""
BKASH_USERNAME=""
BKASH_PASSWORD=""
BKASH_CALLBACK_URL=""
```
## Sandbox validation
Start local server.

```bash
php artisan serve
```

To get create-payment data visit `/bkash-sandbox/create-payment` url

```
http://127.0.0.1:8000/bkash-sandbox/create-payment
```

To get execute-payment data visit `/bkash-sandbox/execute-payment` url

```
http://127.0.0.1:8000/bkash-sandbox/execute-payment
```

> The sandbox routes are available only when `BKASH_SANDBOX=true`. To force them on or off, set `BKASH_SANDBOX_ROUTES=true` or `BKASH_SANDBOX_ROUTES=false` in your .env file.

## Sandbox Uses

included BkashPayment trait in your controller. like:
```
use Msilabs\Bkash\BkashPayment;

class BkashController extends Controller
{
  use BkashPayment;

  //
}
```

create payment

```
public function payment()
{
  // your code 

  $response = $this->createPayment($amount, $invoice_id = null, $dynamic_callback_url = null);

  if(isset($response->bkashURL)) {
    return redirect($response->bkashURL);
  }
}

```

execute payment

```
public function callback(Request $request)
{
  $paymentID = $request->paymentID;
  $status = $request->status;

  if($paymentID && $status == 'success') {
      $response = $this->executePayment($paymentID);

      if(isset($response->transactionStatus) && $response->transactionStatus == 'Completed') {
          $order_id = $response->merchantInvoiceNumber;
          $trxID = $response->trxID;

          // your code
      }

  }

  // your code
}
```

## Production Use

set Live Credentials in .env

```bash
BKASH_SANDBOX=false
BKASH_APP_KEY=""
BKASH_APP_SECRET=""
BKASH_USERNAME=""
BKASH_PASSWORD=""
BKASH_CALLBACK_URL=""
```
