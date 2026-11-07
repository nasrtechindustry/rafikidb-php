<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Transactional SMS through RafikiSMS.
 */
final class Sms
{
    public function __construct(private readonly Client $client)
    {
    }

    public function send(string $phone, string $message): Envelope
    {
        return $this->client->post(
            '/projects/' . $this->client->projectId() . '/sms/send',
            ['phone' => $phone, 'message' => $message],
        );
    }

    /** @param array<int, string> $recipients */
    public function sendBulk(array $recipients, string $message): Envelope
    {
        return $this->client->post(
            '/projects/' . $this->client->projectId() . '/sms/send-bulk',
            ['recipients' => array_values($recipients), 'message' => $message],
        );
    }

    /** Remaining credits on the active RafikiSMS account. */
    public function balance(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/sms/balance');
    }

    /** Sender IDs registered on the active RafikiSMS account. */
    public function senderNames(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/sms/sender-names');
    }

    /** Delivery status for a recipient and provider request ID. */
    public function deliveryReport(string $destAddr, string $requestId): Envelope
    {
        return $this->client->get(
            '/projects/' . $this->client->projectId() . '/sms/delivery-report',
            ['dest_addr' => $destAddr, 'request_id' => $requestId],
        );
    }

    /** Send a one-time password and return its reference ID. */
    public function generateOtp(string $phone): Envelope
    {
        return $this->client->post(
            '/projects/' . $this->client->projectId() . '/sms/otp/generate',
            ['phone' => $phone],
        );
    }

    /** Verify a one-time password against a generated reference ID. */
    public function verifyOtp(string $phone, string $code, string $referenceId): Envelope
    {
        return $this->client->post(
            '/projects/' . $this->client->projectId() . '/sms/otp/verify',
            ['phone' => $phone, 'code' => $code, 'reference_id' => $referenceId],
        );
    }

    /** Recent SMS deliveries (dashboard user session required). */
    public function deliveries(int $limit = 50): Envelope
    {
        return $this->client->get(
            '/projects/' . $this->client->projectId() . '/sms/deliveries',
            ['limit' => (string) $limit],
        );
    }
}
