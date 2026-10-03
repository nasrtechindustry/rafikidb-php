<?php

declare(strict_types=1);

namespace RafikiDB;


/**
 * Raised when the API returns a non-2xx response or a failed envelope.
 */
final class RafikiDBException extends \Exception
{
    public readonly int $status;
    public readonly array $errors;
    public readonly string $errorCode;

    public function __construct(
        string $message,
        int $status,
        array $errors = [],
        string $errorCode = '',
    ) {
        parent::__construct($message);
        $this->status = $status;
        $this->errors = $errors;
        $this->errorCode = $errorCode;
    }

    public static function fromEnvelope(Envelope $envelope, int $status): self
    {
        return new self(
            message: $envelope->message !== '' ? $envelope->message : "Request failed ($status)",
            status: $status,
            errors: $envelope->errors,
            errorCode: self::codeForStatus($status),
        );
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'invalid_input',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            429 => 'quota_exceeded',
            default => 'internal_error',
        };
    }
}
