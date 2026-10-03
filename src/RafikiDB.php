<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Typed RafikiDB client facade.
 *
 * $db = RafikiDB::create(project_id, api_key, base_url);
 * $rows = $db->from('messages')->select('id, message')->limit(10)->execute();
 */
final class RafikiDB
{
    public readonly Client $client;
    public readonly Auth $auth;
    public readonly Realtime $realtime;
    public readonly Storage $storage;
    public readonly Env $env;
    public readonly Secrets $secrets;
    public readonly Webhooks $webhooks;
    public readonly Functions $functions;
    public readonly Payments $payments;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->auth = new Auth($client);
        $this->realtime = new Realtime($client);
        $this->storage = new Storage($client);
        $this->env = new Env($client);
        $this->secrets = new Secrets($client);
        $this->webhooks = new Webhooks($client);
        $this->functions = new Functions($client);
        $this->payments = new Payments($client);
    }

    public static function create(string $projectId, string $apiKey, ?string $baseUrl = null): self
    {
        return new self(new Client($projectId, $apiKey, $baseUrl));
    }

    public function from(string $table): DataBuilder
    {
        return new DataBuilder($this->client, $table);
    }

    /** @param array<string, mixed>|null $body */
    public function request(string $path, string $method = 'GET', ?array $body = null): Envelope
    {
        return $this->client->request($path, $method, $body);
    }
}