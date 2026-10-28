<?php

namespace Msilabs\Bkash\Exceptions;

use Msilabs\Bkash\BkashResponse;
use RuntimeException;
use Throwable;

class BkashException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?BkashResponse $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(BkashResponse $response): self
    {
        $message = $response->errorMessage() ?? 'bKash request failed.';

        if ($response->errorCode()) {
            $message = "[{$response->errorCode()}] {$message}";
        }

        return new self($message, $response->errorCode(), $response);
    }
}
