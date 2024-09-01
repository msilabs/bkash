<p align="center">
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/dt/msilabs/bkash" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/v/msilabs/bkash" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/l/msilabs/bkash" alt="License"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/github/stars/msilabs/bkash" alt="Github Stars"></a>
</p>

# msilas/bkash

bKash Payment Gateway API for Laravel Framework.

## Requirements

- PHP >= 7.4
- Laravel >= 6

## Get Started

Install `msilabs/bkash` via the [Composer](https://getcomposer.org/) package manager:

```bash
composer require msilabs/bkash
```

Optionally, publish the configuration file

```bash
php artisan vendor:publish --provider="Msilabs\Bkash\BkashServiceProvider"
```

Set up the environment configuration in your .env file:

```bash
BKASH_SANDBOX=true  #for production use false
BKASH_APP_KEY=""
BKASH_APP_SECRET=""
BKASH_USERNAME=""
BKASH_PASSWORD=""
```

## Sandbox validation
Start local server.

```bash
php artisan serve
```

To validate create-payment visit `/bkash-sandbox-validation/create-payment` url

```
http://127.0.0.1:8000/bkash-sandbox-validation/create-payment
```

To validate execute-payment visit `/bkash-sandbox-validation/execute-payment` url

```
http://127.0.0.1:8000/bkash-sandbox-validation/execute-payment
```

for multiple account see this: [Multiple Account](#multiple-account)

## Usage

Include the BkashPayment trait in your controller:

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

  $response = $this->createPayment($amount, $invoice_id, $callback_url);

  return redirect($response->bkashURL);
}

```

execute payment

```
public function callback(Request $request)
{
  $payment_id = $request->paymentID;
  $status = $request->status;

  if($paymentID && $status == 'success') {
      $response = $this->executePayment($paymentID);

      if($response->transactionStatus == 'Completed') {
          $order_id = $response['merchantInvoiceNumber'];
          $trxID = $response['trxID'];

          // your code
      }

  }

  // your code
}
```

## Production Use

Set live credentials in your .env file:

```bash
BKASH_SANDBOX=false
BKASH_APP_KEY=""
BKASH_APP_SECRET=""
BKASH_USERNAME=""
BKASH_PASSWORD=""
```

## Multiple Account

Add multiple accounts in the **config/bkash.php** file. For example, to add `secondary` account:

```bash
"accounts" => [
    "primary" => [
        "sandbox"       => env("BKASH_SANDBOX", true),  #for production use false
        "app_key"       => env("BKASH_APP_KEY"),
        "app_secret"    => env("BKASH_APP_SECRET"),
        "username"      => env("BKASH_USERNAME"),
        "password"      => env("BKASH_PASSWORD"),
    ],
    "secondary" => [
        "sandbox"       => env("BKASH_SECONDARY_SANDBOX", true),  #for production use false
        "app_key"       => env("BKASH_SECONDARY_APP_KEY"),
        "app_secret"    => env("BKASH_SECONDARY_APP_SECRET"),
        "username"      => env("BKASH_SECONDARY_USERNAME"),
        "password"      => env("BKASH_SECONDARY_PASSWORD"),
    ],
    // Add more stores if you need
],
```

Set multiple credentials in your .env file:

```bash
BKASH_SANDBOX=false
BKASH_APP_KEY=""
BKASH_APP_SECRET=""
BKASH_USERNAME=""
BKASH_PASSWORD=""

BKASH_SECONDARY_SANDBOX=false
BKASH_SECONDARY_APP_KEY=""
BKASH_SECONDARY_USERNAME=""
BKASH_SECONDARY_USERNAME=""
BKASH_SECONDARY_PASSWORD=""
```

## Multiple Sandbox validation : example 

To validate **create-payment** for `secondary` account, visit the following URL:

```
http://127.0.0.1:8000/bkash-sandbox-validation/create-payment/secondary
```

To validate **execute-payment** for `secondary` account, visit the following URL:

```
http://127.0.0.1:8000/bkash-sandbox-validation/execute-payment/secondary
```