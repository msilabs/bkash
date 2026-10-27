<?php

namespace Msilabs\Bkash;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Msilabs\Bkash\Exceptions\BkashException;

class BkashClient
{
    protected const SECRET_KEYS = ['app_secret', 'password', 'username', 'Authorization', 'id_token', 'refresh_token'];

    /**
     * @param  array<string, mixed>  $credentials  sandbox, app_key, app_secret, username, password, base_url
     * @param  array<string, mixed>  $options  urls, timeout, log
     */
    public function __construct(
        protected string $name,
        protected array $credentials,
        protected Cache $cache,
        protected array $options = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function isSandbox(): bool
    {
        return filter_var($this->credentials['sandbox'] ?? true, FILTER_VALIDATE_BOOL);
    }

    public function baseUrl(): string
    {
        $url = $this->credentials['base_url']
            ?? ($this->isSandbox() ? $this->options['urls']['sandbox'] : $this->options['urls']['production']);

        return rtrim($url, '/');
    }

    /*
    |--------------------------------------------------------------------------
    | Payment (without agreement)
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $extra  e.g. subMerchantName, merchantAssociationInfo
     */
    public function createPayment(
        string|int|float $amount,
        string $invoiceNumber,
        string $callbackUrl,
        ?string $payerReference = null,
        array $extra = [],
    ): BkashResponse {
        return $this->call('payment/create', array_merge([
            'payerReference' => $payerReference ?? $invoiceNumber,
            'callbackURL' => $callbackUrl,
            'amount' => $this->formatAmount($amount),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $invoiceNumber,
        ], $extra));
    }

    public function executePayment(string $paymentId): BkashResponse
    {
        return $this->call('payment/execute', ['paymentId' => $paymentId]);
    }

    /*
    |--------------------------------------------------------------------------
    | Agreement (one-click payment)
    |--------------------------------------------------------------------------
    */

    public function createAgreement(string $payerReference, string $callbackUrl): BkashResponse
    {
        return $this->call('agreement/create', [
            'payerReference' => $payerReference,
            'callbackURL' => $callbackUrl,
        ]);
    }

    public function executeAgreement(string $agreementId): BkashResponse
    {
        return $this->call('agreement/execute', ['agreementId' => $agreementId]);
    }

    public function queryAgreement(string $agreementId): BkashResponse
    {
        return $this->call('query/agreement', ['agreementId' => $agreementId]);
    }

    public function cancelAgreement(string $agreementId): BkashResponse
    {
        return $this->call('agreement/cancel', ['agreementId' => $agreementId]);
    }

    /**
     * @param  array<string, mixed>  $extra  e.g. subMerchantName, merchantAssociationInfo
     */
    public function createPaymentWithAgreement(
        string $agreementId,
        string|int|float $amount,
        string $invoiceNumber,
        string $callbackUrl,
        array $extra = [],
    ): BkashResponse {
        return $this->call('payment-with-agreement/create', array_merge([
            'agreementId' => $agreementId,
            'callbackURL' => $callbackUrl,
            'amount' => $this->formatAmount($amount),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $invoiceNumber,
        ], $extra));
    }

    public function executePaymentWithAgreement(string $paymentId, string $agreementId): BkashResponse
    {
        return $this->call('payment-with-agreement/execute', [
            'paymentId' => $paymentId,
            'agreementId' => $agreementId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Query & Search
    |--------------------------------------------------------------------------
    */

    public function queryPayment(string $paymentId): BkashResponse
    {
        return $this->call('query/payment', ['paymentId' => $paymentId]);
    }

    public function searchTransaction(string $trxId): BkashResponse
    {
        return $this->call('general/search-transaction', ['trxId' => $trxId]);
    }

    /*
    |--------------------------------------------------------------------------
    | Refund
    |--------------------------------------------------------------------------
    */

    public function refund(
        string $paymentId,
        string $trxId,
        string|int|float $amount,
        string $reason,
        string $sku = 'N/A',
    ): BkashResponse {
        return $this->call('refund/payment/transaction', [
            'paymentId' => $paymentId,
            'trxId' => $trxId,
            'refundAmount' => $this->formatAmount($amount),
            'sku' => $sku,
            'reason' => $reason,
        ]);
    }

    public function refundStatus(string $paymentId, string $trxId): BkashResponse
    {
        return $this->call('refund/payment/status', [
            'paymentId' => $paymentId,
            'trxId' => $trxId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    */

    /**
     * Get a valid id_token, from cache when possible.
     *
     * @throws BkashException
     */
    public function token(bool $fresh = false): string
    {
        if (! $fresh && $token = $this->cache->get($this->cacheKey('id_token'))) {
            return $token;
        }

        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            return $this->obtainToken($fresh);
        }

        return $store->lock($this->cacheKey('lock'), 15)->block(15, function () use ($fresh) {
            // Another process may have fetched a token while we waited for the lock.
            if (! $fresh && $token = $this->cache->get($this->cacheKey('id_token'))) {
                return $token;
            }

            return $this->obtainToken($fresh);
        });
    }

    public function forgetToken(): void
    {
        $this->cache->forget($this->cacheKey('id_token'));
        $this->cache->forget($this->cacheKey('refresh_token'));
    }

    protected function obtainToken(bool $fresh): string
    {
        $refreshToken = $fresh ? null : $this->cache->get($this->cacheKey('refresh_token'));

        $response = null;

        if ($refreshToken) {
            $response = $this->requestToken('auth/refresh-token', ['refresh_token' => $refreshToken]);
        }

        if (! $response || $response->failed() || ! $response->id_token) {
            $response = $this->requestToken('auth/grant-token');
        }

        if ($response->failed() || ! $response->id_token) {
            $this->forgetToken();

            throw new BkashException(
                "Unable to get bKash token for account [{$this->name}]: ".$response->errorMessage(),
                $response->errorCode(),
                $response,
            );
        }

        $ttl = max(60, (int) ($response->expires_in ?? 3600) - 300);

        $this->cache->put($this->cacheKey('id_token'), $response->id_token, $ttl);

        if ($response->refresh_token) {
            $this->cache->put($this->cacheKey('refresh_token'), $response->refresh_token, 60 * 60 * 24 * 29);
        }

        return $response->id_token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function requestToken(string $endpoint, array $payload = []): BkashResponse
    {
        foreach (['app_key', 'app_secret', 'username', 'password'] as $key) {
            if (empty($this->credentials[$key])) {
                throw new BkashException("bKash account [{$this->name}] is missing [{$key}].");
            }
        }

        return $this->send($endpoint, array_merge([
            'app_key' => $this->credentials['app_key'],
            'app_secret' => $this->credentials['app_secret'],
        ], $payload), [
            'username' => $this->credentials['username'],
            'password' => $this->credentials['password'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    /**
     * Send an authorized request. Retries once with a fresh token on HTTP 401.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function call(string $endpoint, array $payload): BkashResponse
    {
        $response = $this->send($endpoint, $payload, $this->authHeaders($this->token()));

        if ($response->status() === 401) {
            $response = $this->send($endpoint, $payload, $this->authHeaders($this->token(fresh: true)));
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => $token,
            'X-App-Key' => (string) $this->credentials['app_key'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     *
     * @throws BkashException
     */
    protected function send(string $endpoint, array $payload, array $headers): BkashResponse
    {
        $url = $this->baseUrl().'/tokenized-checkout/'.$endpoint;

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) ($this->options['timeout'] ?? 30))
                ->withHeaders($headers)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            $this->log($endpoint, $headers, $payload, null, $e->getMessage());

            throw new BkashException("Could not connect to bKash [{$endpoint}]: {$e->getMessage()}", previous: $e);
        }

        $data = $response->json();

        $this->log($endpoint, $headers, $payload, $response->status(), is_array($data) ? $data : $response->body());

        if (! is_array($data)) {
            throw new BkashException("Invalid response from bKash [{$endpoint}] with HTTP status {$response->status()}.");
        }

        return new BkashResponse($data, $response->status());
    }

    protected function log(string $endpoint, array $headers, array $payload, ?int $status, mixed $response): void
    {
        if (! ($this->options['log']['enabled'] ?? false)) {
            return;
        }

        Log::channel($this->options['log']['channel'] ?? null)->info("bKash [{$this->name}] {$endpoint}", [
            'url' => $this->baseUrl().'/tokenized-checkout/'.$endpoint,
            'headers' => $this->mask($headers),
            'request' => $this->mask($payload),
            'status' => $status,
            'response' => is_array($response) ? $this->mask($response) : $response,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mask(array $data): array
    {
        foreach (self::SECRET_KEYS as $key) {
            if (isset($data[$key])) {
                $data[$key] = '********';
            }
        }

        return $data;
    }

    protected function formatAmount(string|int|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function cacheKey(string $suffix): string
    {
        $prefix = $this->options['cache_prefix'] ?? 'bkash';

        $identity = md5($this->baseUrl().'|'.$this->credentials['app_key'].'|'.$this->credentials['username']);

        return "{$prefix}.{$this->name}.{$identity}.{$suffix}";
    }
}
