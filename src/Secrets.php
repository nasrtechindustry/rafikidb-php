<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Module APIs: storage, env vars, secrets, webhooks, edge functions, payments.
 */

final class Secrets
{
    public function __construct(private readonly Client $client)
    {
    }

    public function list(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/secrets');
    }

    public function set(string $key, string $value, ?string $description = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/secrets', [
            'key' => $key, 'value' => $value, 'description' => $description,
        ]);
    }

    public function reveal(string $secretId): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/secrets/' . $secretId . '/value');
    }

    public function update(string $secretId, ?string $value = null, ?string $description = null): Envelope
    {
        return $this->client->patch('/projects/' . $this->client->projectId() . '/secrets/' . $secretId, [
            'value' => $value, 'description' => $description,
        ]);
    }

    public function remove(string $secretId): Envelope
    {
        return $this->client->delete('/projects/' . $this->client->projectId() . '/secrets/' . $secretId);
    }
}
