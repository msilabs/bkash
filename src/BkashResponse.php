<?php

namespace Msilabs\Bkash;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use JsonSerializable;
use LogicException;
use Msilabs\Bkash\Exceptions\BkashException;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class BkashResponse implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected array $data,
        protected int $status = 200,
    ) {}

    public function successful(): bool
    {
        if ($this->status < 200 || $this->status >= 300) {
            return false;
        }

        if (isset($this->data['externalCode']) || isset($this->data['errorCode'])) {
            return false;
        }

        if (isset($this->data['statusCode']) && $this->data['statusCode'] !== '0000') {
            return false;
        }

        return true;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * True when the payment, agreement or refund reached the "Completed" state.
     */
    public function isCompleted(): bool
    {
        if ($this->failed()) {
            return false;
        }

        $status = $this->data['transactionStatus']
            ?? $this->data['agreementStatus']
            ?? $this->data['refundTransactionStatus']
            ?? null;

        return $status === 'Completed';
    }

    public function errorCode(): ?string
    {
        if ($this->successful()) {
            return null;
        }

        $code = $this->data['externalCode']
            ?? $this->data['errorCode']
            ?? $this->data['statusCode']
            ?? null;

        return $code !== null ? (string) $code : (string) $this->status;
    }

    public function errorMessage(): ?string
    {
        if ($this->successful()) {
            return null;
        }

        return $this->data['errorMessageEn']
            ?? $this->data['errorMessage']
            ?? $this->data['statusMessage']
            ?? 'bKash request failed with HTTP status '.$this->status.'.';
    }

    public function errorMessageBn(): ?string
    {
        return $this->successful() ? null : ($this->data['errorMessageBn'] ?? null);
    }

    /**
     * The bKash payment page URL returned by create payment / agreement.
     */
    public function redirectUrl(): ?string
    {
        return $this->data['bkashURL'] ?? $this->data['bKashURL'] ?? null;
    }

    /**
     * The merchant invoice number. Query Payment returns it as "merchantInvoice".
     */
    public function invoiceNumber(): ?string
    {
        return $this->data['merchantInvoiceNumber'] ?? $this->data['merchantInvoice'] ?? null;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * Throw a BkashException if the response failed.
     *
     * @throws BkashException
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw BkashException::fromResponse($this);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->data, $options);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('bKash response is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('bKash response is read-only.');
    }
}
