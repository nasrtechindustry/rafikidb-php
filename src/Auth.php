<?php

declare(strict_types=1);

namespace RafikiDB;

use function RafikiDB\authSession;

/**
 * Project auth flows. Sessions are stored on the client automatically.
 */
final class Auth
{
    public function __construct(private readonly Client $client)
    {
    }

    /** @param array<string, mixed>|null $metadata */
    public function signup(string $email, string $password, string $fullName, string $phone, ?array $metadata = null): Envelope
    {
        $env = $this->client->post('/projects/' . $this->client->projectId() . '/auth/signup', [
            'email' => $email,
            'password' => $password,
            'full_name' => $fullName,
            'phone' => $phone,
            'metadata' => $metadata,
        ]);
        return $this->storeSession($env);
    }

    public function login(string $email, string $password): Envelope
    {
        $env = $this->client->post('/projects/' . $this->client->projectId() . '/auth/signin', [
            'email' => $email,
            'password' => $password,
        ]);
        return $this->storeSession($env);
    }

    public function signin(string $email, string $password): Envelope
    {
        return $this->login($email, $password);
    }

    public function otpRequest(string $phone): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/auth/otp/request', [
            'phone' => $phone,
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    public function otpVerify(string $phone, string $code, string $fullName, ?array $metadata = null): Envelope
    {
        $env = $this->client->post('/projects/' . $this->client->projectId() . '/auth/otp/verify', [
            'phone' => $phone,
            'code' => $code,
            'full_name' => $fullName,
            'metadata' => $metadata,
        ]);
        return $this->storeSession($env);
    }

    public function resetPassword(string $email): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/auth/reset-password', [
            'email' => $email,
        ]);
    }

    public function confirmResetPassword(string $email, string $code, string $newPassword): Envelope
    {
        return $this->client->post('/projects/' . $this->client->projectId() . '/auth/reset-password/confirm', [
            'email' => $email,
            'code' => $code,
            'new_password' => $newPassword,
        ]);
    }

    public function signOut(): void
    {
        $this->client->setSession(null);
    }

    public function refresh(string $refreshToken): Envelope
    {
        return $this->client->post('/auth/refresh', ['refresh_token' => $refreshToken]);
    }

    public function logout(string $refreshToken): Envelope
    {
        return $this->client->post('/auth/logout', ['refresh_token' => $refreshToken]);
    }

    private function storeSession(Envelope $env): Envelope
    {
        if ($env->data !== null && is_array($env->data)) {
            $session = authSession($env->data);
            if ($session['tokens']['access_token'] !== '') {
                $this->client->setSession($session);
            }
            return new Envelope($env->success, $env->message, $session, $env->errors);
        }
        return $env;
    }
}