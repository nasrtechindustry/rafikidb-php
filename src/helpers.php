<?php

declare(strict_types=1);

namespace RafikiDB;


/** @return array<string, mixed> */
function record(mixed $value): array
{
    return is_array($value) ? $value : [];
}

/** @param array<string, mixed> $payload */
function projectUser(array $payload): array
{
    return [
        'id' => (string) ($payload['id'] ?? ''),
        'project_id' => (string) ($payload['project_id'] ?? ''),
        'email' => $payload['email'] ?? null,
        'phone' => $payload['phone'] ?? null,
        'full_name' => $payload['full_name'] ?? null,
        'is_active' => (bool) ($payload['is_active'] ?? true),
        'email_verified_at' => $payload['email_verified_at'] ?? null,
        'phone_verified_at' => $payload['phone_verified_at'] ?? null,
        'last_sign_in_at' => $payload['last_sign_in_at'] ?? null,
        'metadata' => $payload['metadata'] ?? null,
        'created_at' => (string) ($payload['created_at'] ?? ''),
        'updated_at' => (string) ($payload['updated_at'] ?? ''),
    ];
}

/** @param array<string, mixed> $payload */
function authSession(array $payload): array
{
    return [
        'user' => projectUser((array) ($payload['user'] ?? [])),
        'tokens' => [
            'access_token' => (string) ($payload['tokens']['access_token'] ?? ''),
            'refresh_token' => (string) ($payload['tokens']['refresh_token'] ?? ''),
        ],
    ];
}

/** @param array<string, mixed> $payload */
function realtimeEvent(array $payload): array
{
    return [
        'id' => (string) ($payload['id'] ?? ''),
        'type' => (string) ($payload['type'] ?? ''),
        'table' => (string) ($payload['table'] ?? ''),
        'project_id' => (string) ($payload['project_id'] ?? ''),
        'record' => record($payload['record'] ?? []),
        'old_record' => isset($payload['old_record']) ? record($payload['old_record']) : null,
        'created_at' => (string) ($payload['created_at'] ?? ''),
    ];
}
