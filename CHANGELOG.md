# Changelog

All notable changes to `msilabs/bkash` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/).

## [3.0.0] - Unreleased

Upgrading from 2.x? See [UPGRADE.md](UPGRADE.md).

### Added

- bKash Tokenized Checkout **v2** API.
- `Bkash` facade. The `BkashPayment` trait is gone.
- Multiple merchants with `Bkash::account('name')`, and runtime or database credentials with `Bkash::using([...])`.
- Refund and refund status: `refund()`, `refundStatus()`.
- Query and search: `queryPayment()`, `searchTransaction()`.
- Agreement (one-click) payments: `createAgreement()`, `executeAgreement()`, `queryAgreement()`, `cancelAgreement()`, `createPaymentWithAgreement()`, `executePaymentWithAgreement()`.
- `BkashResponse` object with `successful()`, `failed()`, `isCompleted()`, `redirectUrl()`, `invoiceNumber()`, `errorCode()`, `errorMessage()`, `errorMessageBn()` and `throw()`.
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

## [2.1.1]

- Last 2.x release (bKash v1.2.0-beta API).
