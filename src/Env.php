<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Module APIs: storage, env vars, secrets, webhooks, edge functions, payments.
 */

final class Env
{
    public function __construct(private readonly Client $client)
    {
    }

    public function list(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/env');
    }

    public function set(string $key, string $value, string $environment = 'production'): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/env', [
            'key' => $key, 'value' => $value, 'environment' => $environment,
        ]);
    }

    public function update(string $varId, ?string $value = null, ?string $environment = null): Envelope
    {
        return $this->client->patch('/projects/' . $this->client->projectId() . '/env/' . $varId, [
            'value' => $value, 'environment' => $environment,
        ]);
    }

    public function remove(string $varId): Envelope
    {
        return $this->client->delete('/projects/' . $this->client->projectId() . '/env/' . $varId);
    }

    /** @param array<string, string> $values */
    public function bulkSet(string $environment, array $values): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/env/bulk', [
            'environment' => $environment, 'values' => $values,
        ]);
    }
}
