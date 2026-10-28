<?php

namespace Msilabs\Bkash;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait BkashPayment
{
    protected function getCallbackUrl()
    {
        return url(config('bkash.callback_url'));
    }

    protected function getFullUrl($url)
    {
        $base_url = config('bkash.sandbox')
            ? config('bkash.sandbox_url')
            : config('bkash.base_url');

        // Ensure there's exactly one slash between base_url and url
        $full_url = rtrim($base_url, '/') . '/' . ltrim($url, '/');

        return $full_url;
    }

    public function getToken($fromCache = true)
    {
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
                    "username" => config('bkash.username'),
                    "password" => config('bkash.password'),
                ])
                ->post($this->getFullUrl("/tokenized/checkout/token/grant"), [
                    "app_key"       => config('bkash.app_key'),
                    "app_secret"    => config('bkash.app_secret'),
                ]);

            // Check if the response status is 200 (OK)
            if (!$response->successful()) {
                throw new \ErrorException("bKash API request failed with status code: " . $response->status());
            }

            $data = json_decode($response->body());

            if (!isset($data->id_token)) {
                throw new \ErrorException("bKash token not found in the response.");
            }

            // Expire the cache a minute before the token itself expires
            $expires_in = isset($data->expires_in) ? (int) $data->expires_in : 3600;

            Cache::put($cacheKey, $data->id_token, max($expires_in - 60, 60));

            return $data->id_token;
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the HTTP request
            throw new \ErrorException("Error retrieving bKash token: " . $e->getMessage());
        }
    }

    public function createPayment($amount, $invoice_number = null, $dynamic_callback_url = null)
    {
        return $this->bkashPost("/tokenized/checkout/create", [
            "amount"                => $amount,
            "currency"              => "BDT",
            "merchantInvoiceNumber" => (string) ($invoice_number ?? (time() . uniqid())),
            "intent"                => "sale",
            "mode"                  => "0011",
            "payerReference"        => "222",
            "callbackURL"           => $dynamic_callback_url ?? $this->getCallbackUrl(),
        ]);
    }

    public function executePayment($payment_id)
    {
        return $this->bkashPost("/tokenized/checkout/execute", [
            'paymentID' => $payment_id,
        ]);
    }

    private function bkashPost($url, array $body)
    {
        $send = function ($token) use ($url, $body) {
            return Http::acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    "Authorization" => $token,
                    "X-App-Key"     => config('bkash.app_key'),
                ])
                ->post($this->getFullUrl($url), $body);
        };

        $response = $send($this->getToken());

        // The cached token may be expired or revoked, retry once with a fresh one
        if ($response->status() === 401) {
            $response = $send($this->getToken(false));
        }

        return json_decode($response->body());
    }

    private function bkashTokenCacheKey()
    {
        $mode = config('bkash.sandbox') ? 'sandbox' : 'live';

        $credentials = md5(config('bkash.app_key') . '|' . config('bkash.username'));

        return "bkash_token_{$mode}_{$credentials}";
    }
}
