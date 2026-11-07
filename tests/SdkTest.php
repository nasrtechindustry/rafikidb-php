<?php

declare(strict_types=1);

namespace RafikiDB\Tests;

use PHPUnit\Framework\TestCase;
use RafikiDB\Client;
use RafikiDB\RafikiDB;
use RafikiDB\RafikiDBException;
use RafikiDB\JoinSpec;

final class SdkTest extends TestCase
{
    public function testDataBuilderQueryParams(): void
    {
        $http = $this->fakeHttp(function (string $url, string $method, ?array $body): array {
            return [['success' => true, 'message' => 'Request was successful', 'data' => []], 200];
        });
        $db = RafikiDB::create('proj-1', 'key-1');
        $db->client->setHttp($http);

        $db->from('profiles')
            ->select('id, nationality')
            ->eq('nationality', 'Tanzania')
            ->order('created_at', ascending: false)
            ->limit(5)
            ->execute();

        $this->assertStringContainsString('/data/profiles?', $http->last['url']);
        $this->assertStringContainsString('nationality=eq.Tanzania', $http->last['url']);
        $this->assertStringContainsString('order=created_at.desc', $http->last['url']);
        $this->assertStringContainsString('limit=5', $http->last['url']);
        $this->assertSame('key-1', $http->last['headers']['X-AFRIBASE-API-Key'] ?? null);
    }

    public function testJoinRoutesToQueryEngine(): void
    {
        $http = $this->fakeHttp(function (string $url, string $method, ?array $body): array {
            return [['success' => true, 'message' => 'ok', 'data' => ['rows' => [['id' => '1']]]], 200];
        });
        $db = RafikiDB::create('proj-1', 'key-1');
        $db->client->setHttp($http);

        $db->from('messages')
            ->select('id, message, users.full_name as full_name')
            ->join(new JoinSpec(table: 'users', fromColumn: 'user_id', toColumn: 'id'))
            ->limit(10)
            ->execute();

        $this->assertStringContainsString('/query/run', $http->last['url']);
        $this->assertSame('messages', $http->last['body']['table_slug']);
        $this->assertSame('users', $http->last['body']['config']['joins'][0]['table']);
    }

    public function testErrorThrows(): void
    {
        $http = $this->fakeHttp(function (string $url, string $method, ?array $body): array {
            return [['success' => false, 'message' => 'Phone is required', 'errors' => ['Phone is required']], 400];
        });
        $db = RafikiDB::create('p', 'k');
        $db->client->setHttp($http);

        try {
            $db->from('profiles')->select()->execute();
            $this->fail('expected exception');
        } catch (RafikiDBException $e) {
            $this->assertSame(400, $e->status);
            $this->assertSame('invalid_input', $e->errorCode);
            $this->assertSame(['Phone is required'], $e->errors);
        }
    }

    public function testOAuthAuthorizeExchangeAndIdToken(): void
    {
        $calls = [];
        $responses = [
            [['success' => true, 'message' => 'ok', 'data' => [
                'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth?state=s',
                'state' => 's',
                'expires_at' => '2026-01-01T00:00:00Z',
            ]], 200],
            [['success' => true, 'message' => 'ok', 'data' => [
                'user' => ['id' => 'u1', 'project_id' => 'proj-1'],
                'tokens' => ['access_token' => 'oauth-token', 'refresh_token' => ''],
            ]], 200],
            [['success' => true, 'message' => 'ok', 'data' => [
                'user' => ['id' => 'u1', 'project_id' => 'proj-1'],
                'tokens' => ['access_token' => 'apple-token', 'refresh_token' => ''],
            ]], 200],
        ];

        $http = $this->fakeHttp(function (string $url, string $method, ?array $body) use (&$calls, &$responses): array {
            $calls[] = ['url' => $url, 'method' => $method, 'body' => $body];
            return array_shift($responses);
        });
        $db = RafikiDB::create('proj-1', 'key-1');
        $db->client->setHttp($http);

        $authorized = $db->auth->signInWithOAuth('google', 'https://app.example.com/auth/callback');
        $this->assertSame('s', $authorized->data['state']);
        $this->assertStringContainsString(
            '/projects/proj-1/auth/oauth/google/authorize',
            $calls[0]['url'],
        );
        $this->assertStringContainsString(
            'redirect_to=https%3A%2F%2Fapp.example.com%2Fauth%2Fcallback',
            $calls[0]['url'],
        );

        $db->auth->exchangeOAuthCode('one-time-code');
        $this->assertSame('one-time-code', $calls[1]['body']['code']);
        $this->assertSame('oauth-token', $db->client->session()['tokens']['access_token']);

        $db->auth->signInWithIdToken('apple', 'header.payload.signature', nonce: 'n1', fullName: 'Asha');
        $this->assertSame('header.payload.signature', $calls[2]['body']['id_token']);
        $this->assertSame('apple-token', $db->client->session()['tokens']['access_token']);
    }

    public function testJoinRequiresColumns(): void
    {
        $db = RafikiDB::create('p', 'k');
        $this->expectException(\InvalidArgumentException::class);
        $db->from('messages')->join(new JoinSpec(table: 'users'));
    }

    public function testEmailAndSmsModules(): void
    {
        $calls = [];
        $responses = array_fill(0, 11, [['success' => true, 'message' => 'ok', 'data' => []], 200]);
        $http = $this->fakeHttp(function (string $url, string $method, ?array $body) use (&$calls, &$responses): array {
            $calls[] = ['url' => $url, 'method' => $method, 'body' => $body];
            return array_shift($responses);
        });
        $db = RafikiDB::create('proj-1', 'key-1');
        $db->client->setHttp($http);

        $db->email->send('customer@example.com', 'Receipt', '<p>Thanks</p>');
        $db->email->listDeliveries(10);
        $db->email->retry('email-1');
        $db->sms->send('255712345678', 'Hello');
        $db->sms->sendBulk(['255712345678', '255621728109'], 'Hello');
        $db->sms->balance();
        $db->sms->senderNames();
        $db->sms->deliveryReport('255712345678', 'REQ 1');
        $db->sms->generateOtp('255712345678');
        $db->sms->verifyOtp('255712345678', '123456', 'ref-1');
        $db->sms->deliveries(25);

        $this->assertStringContainsString('/projects/proj-1/emails/send', $calls[0]['url']);
        $this->assertSame('customer@example.com', $calls[0]['body']['to']);
        $this->assertStringContainsString('/projects/proj-1/emails?limit=10', $calls[1]['url']);
        $this->assertStringContainsString('/projects/proj-1/emails/email-1/retry', $calls[2]['url']);

        $this->assertStringContainsString('/projects/proj-1/sms/send', $calls[3]['url']);
        $this->assertSame('255712345678', $calls[3]['body']['phone']);
        $this->assertStringContainsString('/projects/proj-1/sms/send-bulk', $calls[4]['url']);
        $this->assertSame(['255712345678', '255621728109'], $calls[4]['body']['recipients']);
        $this->assertStringContainsString('/projects/proj-1/sms/balance', $calls[5]['url']);
        $this->assertStringContainsString('/projects/proj-1/sms/sender-names', $calls[6]['url']);
        $this->assertStringContainsString('/projects/proj-1/sms/delivery-report?', $calls[7]['url']);
        $this->assertStringContainsString('dest_addr=255712345678', $calls[7]['url']);
        $this->assertStringContainsString('request_id=REQ+1', $calls[7]['url']);
        $this->assertStringContainsString('/projects/proj-1/sms/otp/generate', $calls[8]['url']);
        $this->assertStringContainsString('/projects/proj-1/sms/otp/verify', $calls[9]['url']);
        $this->assertSame('ref-1', $calls[9]['body']['reference_id']);
        $this->assertStringContainsString('/projects/proj-1/sms/deliveries?limit=25', $calls[10]['url']);
    }

    private function fakeHttp(callable $handler): object
    {
        return new class($handler) {
            public array $last = [];
            public function __construct(private $handler)
            {
            }
            public function __invoke(string $url, string $method, ?array $body): array
            {
                $this->last = ['url' => $url, 'method' => $method, 'body' => $body, 'headers' => ['X-AFRIBASE-API-Key' => 'key-1']];
                return ($this->handler)($url, $method, $body);
            }
        };
    }
}
