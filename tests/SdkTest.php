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

    public function testJoinRequiresColumns(): void
    {
        $db = RafikiDB::create('p', 'k');
        $this->expectException(\InvalidArgumentException::class);
        $db->from('messages')->join(new JoinSpec(table: 'users'));
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
