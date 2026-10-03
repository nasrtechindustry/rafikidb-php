<?php

declare(strict_types=1);

namespace RafikiDB;

use function RafikiDB\realtimeEvent;

/**
 * Realtime subscriptions.
 *
 * SSE transport by default (stdlib only). Use it in long-running processes
 * (Laravel artisan commands, daemons):
 *
 *     $sub = $db->realtime->subscribe('messages', function ($event) {
 *         // handle INSERT/UPDATE/DELETE
 *     });
 *     $sub->run();   // blocks; Ctrl+C or ->close() from another process stops
 *
 * `close()` writes a stop marker that run() polls between frames.
 */
final class Realtime
{
    public function __construct(private readonly Client $client)
    {
    }

    /**
     * @param callable(array<string, mixed>): void $onEvent
     * @param array{events?: string[], transport?: string} $options
     */
    public function subscribe(string $table, callable $onEvent, array $options = []): Subscription
    {
        $events = $options['events'] ?? null;
        $transport = $options['transport'] ?? 'sse';

        if ($transport === 'websocket') {
            return $this->subscribeWebSocket($table, $onEvent, $events);
        }
        return $this->subscribeSSE($table, $onEvent, $events);
    }

    /**
     * @param callable(array<string, mixed>): void $onEvent
     * @param string[]|null $events
     */
    private function subscribeSSE(string $table, callable $onEvent, ?array $events): Subscription
    {
        $query = http_build_query(['api_key' => $this->client->apiKey(), 'table' => $table]);
        if ($events !== null && $events !== []) {
            $query .= '&events=' . implode(',', $events);
        }
        $url = $this->client->baseUrl() . '/projects/' . $this->client->projectId() . '/realtime/events?' . $query;

        $sub = new Subscription();
        $sub->run = function (callable $isStopped) use ($url, $onEvent, $events): void {
            while (!$isStopped()) {
                $handle = @fopen($url, 'r');
                if ($handle === false) {
                    sleep(3);
                    continue;
                }
                $buffer = '';
                while (!$isStopped() && !feof($handle)) {
                    $chunk = fread($handle, 4096);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $buffer .= $chunk;
                    while (($sep = strpos($buffer, "\n\n")) !== false) {
                        $frame = substr($buffer, 0, $sep);
                        $buffer = substr($buffer, $sep + 2);
                        foreach (explode("\n", $frame) as $line) {
                            if (!str_starts_with($line, 'data:')) {
                                continue;
                            }
                            $payload = json_decode(trim(substr($line, 5)), true);
                            if (!is_array($payload)) {
                                continue;
                            }
                            $event = realtimeEvent($payload);
                            if ($events !== null && !in_array($event['type'], $events, true)) {
                                continue;
                            }
                            $onEvent($event);
                        }
                    }
                }
                fclose($handle);
                if (!$isStopped()) {
                    sleep(3);
                }
            }
        };

        return $sub;
    }

    /**
     * @param callable(array<string, mixed>): void $onEvent
     * @param string[]|null $events
     */
    private function subscribeWebSocket(string $table, callable $onEvent, ?array $events): Subscription
    {
        $env = $this->client->post('/projects/' . $this->client->projectId() . '/realtime/connect?table=' . rawurlencode($table));
        $info = is_array($env->data) ? $env->data : [];
        $url = str_replace(['http://', 'https://'], ['ws://', 'wss://'], (string) ($info['url'] ?? '')) . '/connection/websocket';
        $token = (string) ($info['token'] ?? '');
        $channel = (string) ($info['channel'] ?? '');

        $sub = new Subscription();
        $sub->run = function (callable $isStopped) use ($url, $token, $channel, $onEvent, $events): void {
            while (!$isStopped()) {
                $socket = @stream_socket_client($url, $errno, $errstr, 10);
                if ($socket === false) {
                    sleep(3);
                    continue;
                }
                stream_set_timeout($socket, 25);
                $this->wsSend($socket, json_encode(['id' => 1, 'connect' => ['token' => $token]]));
                while (!$isStopped() && !feof($socket)) {
                    $payload = $this->wsRead($socket);
                    if ($payload === null) {
                        break;
                    }
                    if ($payload === '') {
                        continue;
                    }
                    $decoded = json_decode($payload, true);
                    $push = is_array($decoded) ? ($decoded['push'] ?? null) : null;
                    if (!is_array($push) || ($push['channel'] ?? '') !== $channel) {
                        continue;
                    }
                    $data = is_array($push['pub'] ?? null) ? ($push['pub']['data'] ?? []) : ($push['data'] ?? []);
                    $event = realtimeEvent(is_array($data) ? $data : []);
                    if ($events !== null && !in_array($event['type'], $events, true)) {
                        continue;
                    }
                    $onEvent($event);
                }
                @fclose($socket);
                if (!$isStopped()) {
                    sleep(3);
                }
            }
        };

        return $sub;
    }

    private function wsSend($socket, string $payload): void
    {
        $len = strlen($payload);
        $header = chr(0x81);
        if ($len < 126) {
            $header .= chr(0x80 | $len);
        } elseif ($len < 65536) {
            $header .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $header .= chr(0x80 | 127) . pack('J', $len);
        }
        $key = random_bytes(4);
        $masked = $payload ^ str_repeat($key, intdiv($len + 3, 4));
        fwrite($socket, $header . $key . $masked);
    }

    private function wsRead($socket): ?string
    {
        $head = fread($socket, 2);
        if ($head === false || strlen($head) < 2) {
            return null;
        }
        $b0 = ord($head[0]);
        $len = ord($head[1]) & 0x7F;
        if ($len === 126) {
            $ext = fread($socket, 2);
            if ($ext === false || strlen($ext) < 2) {
                return null;
            }
            $len = unpack('n', $ext)[1];
        } elseif ($len === 127) {
            $ext = fread($socket, 8);
            if ($ext === false || strlen($ext) < 8) {
                return null;
            }
            $len = unpack('J', $ext)[1];
        }
        if (($b0 & 0x80) !== 0) {
            fread($socket, 4); // mask key (server frames are unmasked; ignore)
        }
        $payload = '';
        while (strlen($payload) < $len) {
            $chunk = fread($socket, $len - strlen($payload));
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $payload .= $chunk;
        }
        return $payload;
    }
}

/**
 * A stoppable subscription. run() blocks; close() writes a stop marker that
 * run() polls, so a subscription can be stopped from another process.
 */
final class Subscription
{
    private string $stopFile;
    /** @var callable(callable(): bool): void|null */
    public $run = null;

    public function __construct()
    {
        $this->stopFile = sys_get_temp_dir() . '/rafikidb_stop_' . bin2hex(random_bytes(6));
    }

    /** Blocks until close() is called or the stream ends. */
    public function run(): void
    {
        $isStopped = fn (): bool => $this->stopped();
        if ($this->run !== null) {
            ($this->run)($isStopped);
        }
    }

    public function close(): void
    {
        @touch($this->stopFile);
    }

    public function stopped(): bool
    {
        return file_exists($this->stopFile);
    }
}