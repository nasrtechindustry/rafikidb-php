<?php

declare(strict_types=1);

namespace RafikiDB;


/**
 * Standard API envelope returned by every endpoint.
 */
final class Envelope
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly mixed $data = null,
        public readonly array $errors = [],
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            success: (bool) ($payload['success'] ?? false),
            message: (string) ($payload['message'] ?? ''),
            data: $payload['data'] ?? null,
            errors: (array) ($payload['errors'] ?? []),
        );
    }
}
