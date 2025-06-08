<?php

namespace Msilabs\Bkash;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait BkashPayment
{
    protected function getCallbackUrl()
    {
        return url(config('bkash.callback_url'));;
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
        // Check if the token is already cached
        $cachedToken = Cache::get("bkash_token}");

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
    
            $data = $response->object();
    
            if (!isset($data->id_token)) {
                throw new \ErrorException("bKash token not found in the response.");
            }
    
            Cache::put("bkash_token", $data->id_token, 3600); // Cache the token for 1 hour
        
            return $data->id_token;
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the HTTP request
            throw new \ErrorException("Error retrieving bKash token: " . $e->getMessage());
        }
    }

    public function createPayment($amount, $invoice_number = null, $dynamic_callback_url = null)
    {
        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                "Authorization" => $this->getToken(),
                "X-App-Key"     => config('bkash.app_key'),
            ])
            ->post($this->getFullUrl("/tokenized/checkout/create"), [
                "amount"                => $amount, 
                "currency"              => "BDT",
                "merchantInvoiceNumber" => (string) ($invoice_number ?? (time() . uniqid())),
                "intent"                => "sale",
                "mode"                  => "0011",
                "payerReference"        => "222",
                "callbackURL"           => $dynamic_callback_url ?? $this->getCallbackUrl(),
            ]);

       return $response->object();
    }

    public function executePayment($payment_id)
    {
        $response = Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                "Authorization" => $this->getToken(),
                "X-App-Key"     => config('bkash.app_key'),
            ])
            ->post($this->getFullUrl("/tokenized/checkout/execute"), [
                'paymentID' => $payment_id,
            ]);

       return $response->object();
    }
}
