<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Module APIs: storage, env vars, secrets, webhooks, edge functions, payments.
 */

final class Payments
{
    public function __construct(private readonly Client $client)
    {
    }

    public function getSettings(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/payments/settings');
    }

    public function updateSettings(?string $mpesaConsumerKey = null, ?string $mpesaConsumerSecret = null, ?string $snippeApiKey = null): Envelope
    {
        return $this->client->put('/projects/' . $this->client->projectId() . '/payments/settings', [
            'mpesa_consumer_key' => $mpesaConsumerKey,
            'mpesa_consumer_secret' => $mpesaConsumerSecret,
            'snippe_api_key' => $snippeApiKey,
        ]);
    }

    public function stkPush(string $phone, float $amount, string $description): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/payments/mpesa/stk-push', [
            'phone' => $phone, 'amount' => $amount, 'description' => $description,
        ]);
    }

    public function snippeInitiate(float $amount, string $phone, string $firstName, string $lastName, ?string $email = null, ?string $description = null, ?string $orderId = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/payments/snippe/initiate', [
            'amount' => $amount, 'phone' => $phone, 'first_name' => $firstName, 'last_name' => $lastName,
            'email' => $email, 'description' => $description, 'order_id' => $orderId,
        ]);
    }

    public function snippeSession(float $amount, ?string $description = null, ?string $redirectUrl = null, ?string $customerName = null, ?string $customerPhone = null, ?string $orderId = null, ?int $expiresIn = null): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/payments/snippe/session', [
            'amount' => $amount, 'description' => $description, 'redirect_url' => $redirectUrl,
            'customer_name' => $customerName, 'customer_phone' => $customerPhone,
            'order_id' => $orderId, 'expires_in' => $expiresIn,
        ]);
    }

    public function snippeStatus(string $reference): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/payments/snippe/status/' . $reference);
    }

    public function listTransactions(): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/payments/transactions');
    }

    public function getTransaction(string $transactionId): Envelope
    {
        return $this->client->get('/projects/' . $this->client->projectId() . '/payments/transactions/' . $transactionId);
    }
}