<?php

namespace Msilabs\Bkash;

use Illuminate\Support\Facades\Http;

trait BkashPayment
{
    protected function getCallbackUrl()
    {
        return config('bkash.sandbox')
            ? url("/bkash-sandbox/execute-payment")
            : url(config('bkash.callback_url'));
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

    public function getToken()
    {
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
    
        $data = $response->object();
    
        return $data->id_token;
    }

    public function createPayment($amount, $invoice_number = null)
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
                "callbackURL"           => $this->getCallbackUrl(),
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

       return $response->json();
    }
}