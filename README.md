<p align="center">
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/dt/msilabs/bkash" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/v/msilabs/bkash" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/msilabs/bkash"><img src="https://img.shields.io/packagist/l/msilabs/bkash" alt="License"></a>
</p>

# msilas/bkash

bKash Payment Gateway API for Laravel Framework.

## Requirements

- PHP >=7.4
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

## Production Uses

set Live Credentials in .env

```bash
BKASH_SANDBOX=false
BKASH_APP_KEY=""
BKASH_APP_SECRET=""
BKASH_USERNAME=""
BKASH_PASSWORD=""
BKASH_CALLBACK_URL=""
```

included BkashPayment trait in your controller
```
use use Msilabs\Bkash\BkashPayment;
```

create payment

```
@method public $this->createPayment($amount, $invoice_id = null)
```

execute payment

```
@method public $this->executePayment($payment_id)
```
