<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Edge functions: sandboxed JavaScript/TypeScript with deploy + invoke.
 */
final class Functions
{
    public function __construct(private readonly Client $client)
    {
    }

    public function list(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/functions');
    }

    public function create(string $name, string $runtime = 'javascript', ?string $code = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/functions', [
            'name' => $name, 'runtime' => $runtime, 'code' => $code,
        ]);
    }

    public function get(string $fnId): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/functions/' . $fnId);
    }

    public function update(string $fnId, ?string $name = null, ?string $runtime = null, ?string $code = null, ?string $status = null): Envelope
    {
        return $this->client->patch('/projects/' . $this->client->projectId() . '/functions/' . $fnId, [
            'name' => $name, 'runtime' => $runtime, 'code' => $code, 'status' => $status,
        ]);
    }

    public function remove(string $fnId): Envelope
    {
        return $this->client->delete('/projects/' . $this->client->projectId() . '/functions/' . $fnId);
    }

    public function deploy(string $fnId): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/functions/' . $fnId . '/deploy');
    }

    /** @param array<string, string>|null $headers */
    public function invoke(string $fnId, string $method = 'POST', ?string $path = null, ?array $headers = null, ?string $body = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/functions/' . $fnId . '/invoke', [
            'method' => $method, 'path' => $path, 'headers' => $headers, 'body' => $body,
        ]);
    }
}
