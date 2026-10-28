# Changelog

All notable changes to `msilabs/bkash` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed

- The token cache lock now lasts long enough to cover a refresh and a grant request, so a second process can no longer request a token while the first is still waiting on bKash.
- A timeout while waiting for the token lock now throws `BkashException` instead of `LockTimeoutException`.
- Credentials with a missing `app_key` or `username` key now throw a clear `BkashException` instead of an "Undefined array key" error.
- `BkashResponse::successful()` also accepts an integer `statusCode` of `0`.

### Changed

- Logs now mask customer wallet numbers (`customerMsisdn`, `payerAccount`) and keep only the last 4 digits.

## [3.1.0] - 2026-10-28

### Added

- `BkashResponse::invoiceNumber()` returns the merchant invoice number from both `merchantInvoiceNumber` (Execute Payment) and `merchantInvoice` (Query Payment).

## [3.0.0] - 2026-10-28

Upgrading from 2.x? See [UPGRADE.md](UPGRADE.md).

### Added

- bKash Tokenized Checkout **v2** API.
- `Bkash` facade. The `BkashPayment` trait is gone.
- Multiple merchants with `Bkash::account('name')`, and runtime or database credentials with `Bkash::using([...])`.
- Refund and refund status: `refund()`, `refundStatus()`.
- Query and search: `queryPayment()`, `searchTransaction()`.
- Agreement (one-click) payments: `createAgreement()`, `executeAgreement()`, `queryAgreement()`, `cancelAgreement()`, `createPaymentWithAgreement()`, `executePaymentWithAgreement()`.
- `BkashResponse` object with `successful()`, `failed()`, `isCompleted()`, `redirectUrl()`, `errorCode()`, `errorMessage()`, `errorMessageBn()` and `throw()`.
- `BkashException` with `errorCode` and `response`.
- Token refresh with the refresh token, a cache lock against concurrent token requests, and one automatic retry on HTTP 401.
- Optional request and response logging, with secrets masked.
- Configurable cache store, timeout and base URLs (globally or per account).
- Test suite and GitHub Actions CI for PHP 8.2–8.5 and Laravel 9–13.

### Changed

- Requires PHP 8.2+ and Laravel 9.33+ (older 9.x releases allow a Carbon version that breaks on PHP 8.2).
- bKash business errors (for example insufficient balance) now come back as a failed `BkashResponse` instead of an exception.
- Sandbox validation routes moved to `/bkash-sandbox/*`. They are off by default and only work for sandbox accounts.
- Unknown account names now throw `InvalidArgumentException` instead of silently falling back to `primary`.
- `payerReference` defaults to the invoice number (2.x always sent `"222"`).

### Removed

- `Msilabs\Bkash\BkashPayment` trait.
- bKash v1.2.0-beta API support. Stay on 2.x if your merchant account only works with v1.2.0-beta.
- Support for PHP < 8.2 and Laravel < 9.

## [2.2.0] - 2026-10-28

Last 2.x release (bKash v1.2.0-beta API).

### Added

- `queryPayment()` for the payment status API.

### Fixed

- Sandbox validation routes register only when an account is in sandbox mode (override with `BKASH_SANDBOX_ROUTES`), and reject live accounts.
- The selected account is stored per instance instead of in a static property.
- The token cache key includes the mode and credentials, the token expires from cache before bKash expires it, and requests retry once on HTTP 401.
- Exception messages include the bKash `statusCode` and `statusMessage`.

### Changed

- Requires Laravel 7.0+. Laravel 6 never had the HTTP client.

## [2.1.1]

- bKash v1.2.0-beta API.
