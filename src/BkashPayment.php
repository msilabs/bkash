<?php

namespace Msilabs\Bkash;

use Illuminate\Support\Facades\Http;

trait BkashPayment
{
    private static $account = 'primary';

    protected function getAccount()
    {
        return self::$account;
    }

    protected function setAccount($account)
    {
        if (config()->has("bkash.accounts.{$account}")) {
            self::$account = $account;
        }
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

    public function getToken()
    {
        $account = $this->getAccount();
    
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
    
            // Check if the response status is 200 (OK)
            if (!$response->successful()) {
                throw new \ErrorException("bKash API request failed with status code: " . $response->status());
            }
    
            $data = $response->object();
    
            if (!isset($data->id_token)) {
                throw new \ErrorException("bKash token not found in the response.");
            }
    
            return $data->id_token;
    
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the HTTP request
            throw new \ErrorException("Error retrieving bKash token: " . $e->getMessage());
        }
    }

    public function createPayment($amount, $invoice_number, $callback_url)
    {
        $account = $this->getAccount();

        try {
            $response = Http::acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    "Authorization" => $this->getToken(),
                    "X-App-Key"     => config("bkash.accounts.{$account}.app_key"),
                ])
                ->post($this->getFullUrl("/tokenized/checkout/create"), [
                    "amount"                => $amount, 
                    "currency"              => "BDT",
                    "merchantInvoiceNumber" => (string) ($invoice_number),
                    "intent"                => "sale",
                    "mode"                  => "0011",
                    "payerReference"        => "222",
                    "callbackURL"           => $callback_url,
                ]);

            if (!$response->successful()) {
                throw new \ErrorException("bKash API request failed with status code: " . $response->status());
            }

            $data = $response->object();

            if (!isset($data->paymentID)) {
                throw new \ErrorException("Payment creation failed. No payment ID received.");
            }

            if (!isset($data->bkashURL)) {
                throw new \ErrorException("Payment creation failed. No bkash URL received.");
            }

            return $data;
        } catch (\Exception $e) {
            // Handle exceptions that may occur during the HTTP request
            throw new \ErrorException("Error creating payment: " . $e->getMessage());
        }
    }

    public function executePayment($payment_id)
    {
        $account = $this->getAccount();

        try {
            $response = Http::acceptJson()
                ->contentType('application/json')
                ->withHeaders([
                    "Authorization" => $this->getToken(),
                    "X-App-Key"     => config("bkash.accounts.{$account}.app_key"),
                ])
                ->post($this->getFullUrl("/tokenized/checkout/execute"), [
                    'paymentID' => $payment_id,
                ]);
    
            if (!$response->successful()) {
                throw new \ErrorException("bKash API request failed with status code: " . $response->status());
            }
    
            $data = $response->object();
    
            if (!isset($data->status)) {
                throw new \ErrorException("Payment execution failed. No status received.");
            }
    
            return $data;
    
        } catch (\Exception $e) {
            // Handle exceptions that may occur during the HTTP request
            throw new \ErrorException("Error executing payment: " . $e->getMessage());
        }
    }
    
}