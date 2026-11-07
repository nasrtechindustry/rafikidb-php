<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Transactional email through the project mailer.
 */
final class Email
{
    public function __construct(private readonly Client $client)
    {
    }

    public function send(string $to, string $subject, string $html): Envelope
    {
        return $this->client->post(
            '/projects/' . $this->client->projectId() . '/emails/send',
            ['to' => $to, 'subject' => $subject, 'html' => $html],
        );
    }

    /** Recent deliveries (dashboard user session required). */
    public function listDeliveries(int $limit = 50): Envelope
    {
        return $this->client->get(
            '/projects/' . $this->client->projectId() . '/emails',
            ['limit' => (string) $limit],
        );
    }

    /** Alias kept for parity with the other SDKs. */
    public function list(int $limit = 50): Envelope
    {
        return $this->listDeliveries($limit);
    }

    /** Retry a failed delivery (dashboard user session required). */
    public function retry(string $emailId): Envelope
    {
        return $this->client->post(
            '/projects/' . $this->client->projectId() . '/emails/' . $emailId . '/retry',
        );
    }
}
