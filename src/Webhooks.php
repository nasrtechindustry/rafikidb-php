<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Module APIs: storage, env vars, secrets, webhooks, edge functions, payments.
 */

final class Webhooks
{
    public function __construct(private readonly Client $client)
    {
    }

    public function list(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/webhooks');
    }

    /** @param string[] $events */
    public function create(string $name, string $url, array $events, ?string $secret = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/webhooks', [
            'name' => $name, 'url' => $url, 'events' => $events, 'secret' => $secret,
        ]);
    }

    /** @param string[]|null $events */
    public function update(string $webhookId, ?string $name = null, ?string $url = null, ?array $events = null, ?string $secret = null, ?bool $enabled = null): Envelope
    {
        return $this->client->patch('/projects/' . $this->client->projectId() . '/webhooks/' . $webhookId, [
            'name' => $name, 'url' => $url, 'events' => $events, 'secret' => $secret, 'enabled' => $enabled,
        ]);
    }

    public function remove(string $webhookId): Envelope
    {
        return $this->client->delete('/projects/' . $this->client->projectId() . '/webhooks/' . $webhookId);
    }

    public function listDeliveries(string $webhookId): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/webhooks/' . $webhookId . '/deliveries');
    }

    public function send(string $webhookId): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/webhooks/' . $webhookId . '/send');
    }

    public function retryDelivery(string $webhookId, string $deliveryId): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/webhooks/' . $webhookId . '/deliveries/' . $deliveryId . '/retry');
    }
}
