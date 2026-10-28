<?php

namespace Msilabs\Bkash;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait BkashPayment
{
    private $bkashAccount = 'primary';

    protected function getAccount()
    {
        return $this->bkashAccount;
    }

    protected function setAccount($account)
    {
        if (config()->has("bkash.accounts.{$account}")) {
            $this->bkashAccount = $account;

            return;
        }

        Log::warning("bKash account [{$account}] not found in config, using [{$this->bkashAccount}] account instead.");
    }

    protected function getFullUrl($url)
    {
        $account = $this->getAccount();

        $base_url = config("bkash.accounts.{$account}.sandbox")
            ? config('bkash.sandbox_url')
            : config('bkash.base_url');

        // Ensure there's exactly one slash between base_url and url
        $full_url = rtrim($base_url, '/') . '/' . ltrim($url, '/');

        return $full_url;
    }

    public function getToken($fromCache = true)
    {
        $account = $this->getAccount();

        $cacheKey = $this->bkashTokenCacheKey();

        // Check if the token is already cached
        $cachedToken = Cache::get($cacheKey);

        if ($cachedToken && $fromCache) {
            return $cachedToken;
        }

        try {
            $response = Http::acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    "username"  => config("bkash.accounts.{$account}.username"),
                    "password"  => config("bkash.accounts.{$account}.password"),
                ])
                ->post($this->getFullUrl("/tokenized/checkout/token/grant"), [
                    "app_key"       => config("bkash.accounts.{$account}.app_key"),
                    "app_secret"    => config("bkash.accounts.{$account}.app_secret"),
                ]);

            $data = json_decode($response->body());

            // Check if the response status is 200 (OK)
            if (!$response->successful()) {
                throw new \ErrorException($this->bkashErrorMessage($data, "bKash API request failed with status code: " . $response->status()));
            }

            if (!isset($data->id_token)) {
                throw new \ErrorException($this->bkashErrorMessage($data, "bKash token not found in the response."));
            }

            // Expire the cache a minute before the token itself expires
            $expires_in = isset($data->expires_in) ? (int) $data->expires_in : 3600;

            Cache::put($cacheKey, $data->id_token, max($expires_in - 60, 60));

            return $data->id_token;

        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the HTTP request
            throw new \ErrorException("Error retrieving bKash token: " . $e->getMessage(), 0, E_ERROR, __FILE__, __LINE__, $e);
        }
    }

    public function createPayment($amount, $invoice_number, $callback_url)
    {
        try {
            $data = $this->bkashPost("/tokenized/checkout/create", [
                "amount"                => $amount,
                "currency"              => "BDT",
                "merchantInvoiceNumber" => (string) ($invoice_number),
                "intent"                => "sale",
                "mode"                  => "0011",
                "payerReference"        => "222",
                "callbackURL"           => $callback_url,
            ]);

            if (!isset($data->paymentID)) {
                throw new \ErrorException($this->bkashErrorMessage($data, "Payment creation failed. No payment ID received."));
            }

            if (!isset($data->bkashURL)) {
                throw new \ErrorException($this->bkashErrorMessage($data, "Payment creation failed. No bkash URL received."));
            }

            return $data;
        } catch (\Exception $e) {
            // Handle exceptions that may occur during the HTTP request
            throw new \ErrorException("Error creating payment: " . $e->getMessage(), 0, E_ERROR, __FILE__, __LINE__, $e);
        }
    }

    public function executePayment($payment_id)
    {
        try {
            return $this->bkashPost("/tokenized/checkout/execute", [
                'paymentID' => $payment_id,
            ]);
        } catch (\Exception $e) {
            // Handle exceptions that may occur during the HTTP request
            throw new \ErrorException("Error executing payment: " . $e->getMessage(), 0, E_ERROR, __FILE__, __LINE__, $e);
        }
    }

    public function queryPayment($payment_id)
    {
        try {
            return $this->bkashPost("/tokenized/checkout/payment/status", [
                'paymentID' => $payment_id,
            ]);
        } catch (\Exception $e) {
            throw new \ErrorException("Error querying payment: " . $e->getMessage(), 0, E_ERROR, __FILE__, __LINE__, $e);
        }
    }

    private function bkashPost($url, array $body)
    {
        $account = $this->getAccount();

        $send = function ($token) use ($account, $url, $body) {
            return Http::acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    "Authorization" => $token,
                    "X-App-Key"     => config("bkash.accounts.{$account}.app_key"),
                ])
                ->post($this->getFullUrl($url), $body);
        };

        $response = $send($this->getToken());

        // The cached token may be expired or revoked, retry once with a fresh one
        if ($response->status() === 401) {
            $response = $send($this->getToken(false));
        }

        $data = json_decode($response->body());

        if (!$response->successful()) {
            throw new \ErrorException($this->bkashErrorMessage($data, "bKash API request failed with status code: " . $response->status()));
        }

        return $data;
    }

    private function bkashTokenCacheKey()
    {
        $account = $this->getAccount();

        $mode = config("bkash.accounts.{$account}.sandbox") ? 'sandbox' : 'live';

        $credentials = md5(config("bkash.accounts.{$account}.app_key") . '|' . config("bkash.accounts.{$account}.username"));

        return "bkash_token_{$account}_{$mode}_{$credentials}";
    }

    private function bkashErrorMessage($data, $default)
    {
        $message = $data->statusMessage ?? $data->errorMessage ?? $data->message ?? null;

        if (!is_string($message) || $message === '') {
            return $default;
        }

        $code = $data->statusCode ?? $data->errorCode ?? null;

        return $code ? "{$default} bKash says: {$message} ({$code})" : "{$default} bKash says: {$message}";
    }
}
