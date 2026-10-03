<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * HTTP client (curl-based, zero dependencies).
 */
final class Client
{
    public const DEFAULT_BASE = 'http://localhost:8080/api/v1';

    private string $baseUrl;
    private ?array $session = null;
    /** @var callable(string, string, ?array): array{array, int}|null */
    private $http = null;

    public function __construct(
        private readonly string $projectId,
        private readonly string $apiKey,
        ?string $baseUrl = null,
        ?array $session = null,
        private readonly float $timeout = 30.0,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE, '/');
        $this->session = $session;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function session(): ?array
    {
        return $this->session;
    }

    public function setSession(?array $session): void
    {
        $this->session = $session;
    }

    /** @param callable(string, string, ?array): array{array, int} $http */
    public function setHttp(callable $http): void
    {
        $this->http = $http;
    }

    public function accessToken(): ?string
    {
        return $this->session['tokens']['access_token'] ?? null;
    }

    /**
     * @param array<string, string>|null $query
     * @param array<string, mixed>|null $body
     */
    public function request(string $path, string $method = 'GET', ?array $body = null, ?array $query = null): Envelope
    {
        $url = $this->baseUrl . $path;
        if ($query !== null && $query !== []) {
            $url .= '?' . http_build_query($query);
        }

        if ($this->http !== null) {
            [$payload, $status] = ($this->http)($url, $method, $body);
            $envelope = Envelope::fromPayload($payload);
            if (!$envelope->success || $status >= 400) {
                throw RafikiDBException::fromEnvelope($envelope, $status);
            }
            return $envelope;
        }

        $headers = [
            'X-AFRIBASE-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $token = $this->accessToken();
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($error !== '') {
            throw new RafikiDBException("Connection error: $error", 0);
        }

        $payload = json_decode($raw !== false && $raw !== '' ? $raw : '{}', true) ?: [];
        $envelope = Envelope::fromPayload($payload);

        if (!$envelope->success || $status >= 400) {
            throw RafikiDBException::fromEnvelope($envelope, $status);
        }

        return $envelope;
    }

    /** @param array<string, string>|null $query */
    public function get(string $path, ?array $query = null): Envelope
    {
        return $this->request($path, 'GET', query: $query);
    }

    /** @param array<string, mixed>|null $body */
    public function post(string $path, ?array $body = null): Envelope
    {
        return $this->request($path, 'POST', body: $body);
    }

    /** @param array<string, mixed>|null $body */
    public function patch(string $path, ?array $body = null): Envelope
    {
        return $this->request($path, 'PATCH', body: $body);
    }

    /** @param array<string, mixed>|null $body */
    public function put(string $path, ?array $body = null): Envelope
    {
        return $this->request($path, 'PUT', body: $body);
    }

    public function delete(string $path): Envelope
    {
        return $this->request($path, 'DELETE');
    }
}