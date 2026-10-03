<?php

declare(strict_types=1);

namespace RafikiDB\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Str;

/**
 * Resolves the project user stored in the session (after db.auth->login())
 * as the authenticated Laravel user.
 */
class RafikiDBUserProvider implements UserProvider
{
    public function __construct(private readonly Application $app)
    {
    }

    public function retrieveById($identifier)
    {
        $user = $this->sessionUser();
        if ($user === null || (string) ($user['id'] ?? '') !== (string) $identifier) {
            return null;
        }
        return $this->toAuthenticatable($user);
    }

    public function retrieveByToken($identifier, $token)
    {
        $user = $this->sessionUser();
        if ($user === null || (string) ($user['id'] ?? '') !== (string) $identifier) {
            return null;
        }
        $accessToken = $this->sessionToken();
        if ($accessToken === null || ! hash_equals((string) $token, $accessToken)) {
            return null;
        }
        return $this->toAuthenticatable($user);
    }

    public function updateRememberToken(AuthenticatableContract $user, $token): void
    {
        // Session-backed: no remember tokens.
    }

    public function retrieveByCredentials(array $credentials)
    {
        $user = $this->sessionUser();
        if ($user === null) {
            return null;
        }
        if (isset($credentials['email']) && strtolower((string) $credentials['email']) !== strtolower((string) ($user['email'] ?? ''))) {
            return null;
        }
        if (isset($credentials['id']) && (string) $credentials['id'] !== (string) ($user['id'] ?? '')) {
            return null;
        }
        return $this->toAuthenticatable($user);
    }

    public function validateCredentials(AuthenticatableContract $user, array $credentials): bool
    {
        return false; // password validation happens through db.auth->login()
    }

    /** @return array<string, mixed>|null */
    private function sessionUser(): ?array
    {
        $session = $this->app->make('rafikidb')->client()->session();
        return $session['user'] ?? null;
    }

    private function sessionToken(): ?string
    {
        $session = $this->app->make('rafikidb')->client()->session();
        return $session['tokens']['access_token'] ?? null;
    }

    /** @param array<string, mixed> $user */
    private function toAuthenticatable(array $user): Authenticatable
    {
        return new class($user) extends Authenticatable {
            /** @var array<string, mixed> */
            protected $rafikidbUser;

            /** @param array<string, mixed> $user */
            public function __construct(array $user)
            {
                $this->rafikidbUser = $user;
                $this->forceFill([
                    'id' => (string) ($user['id'] ?? ''),
                    'email' => $user['email'] ?? null,
                    'name' => $user['full_name'] ?? $user['phone'] ?? $user['email'] ?? null,
                ]);
            }

            /** @return array<string, mixed> */
            public function getAuthIdentifier(): mixed
            {
                return (string) ($this->rafikidbUser['id'] ?? '');
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            /** @return array<string, mixed> */
            public function rafikidbUser(): array
            {
                return $this->rafikidbUser;
            }

            public function getAttribute($key)
            {
                if ($key === 'id') {
                    return $this->getAuthIdentifier();
                }
                return $this->rafikidbUser[$key] ?? parent::getAttribute($key);
            }

            public function __get($key)
            {
                if (array_key_exists($key, $this->rafikidbUser)) {
                    return $this->rafikidbUser[$key];
                }
                return parent::__get($key);
            }
        };
    }
}