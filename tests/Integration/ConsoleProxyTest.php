<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration;

use Nebrix\RemoteConsole\WebSocketProxy;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * End-to-end test of the console relay with a fake upstream WebSocket server
 * in a child process.
 */
final class ConsoleProxyTest extends TestCase
{
    private const ORIGIN = 'https://panel.example.test';

    /** @var resource|null */
    private $process = null;

    private string $portFile = '';

    /** @var list<string> */
    private array $redeemed = [];

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        if ($this->portFile !== '' && is_file($this->portFile)) {
            unlink($this->portFile);
        }
    }

    private function upstreamPort(): int
    {
        $this->portFile = (string) tempnam(sys_get_temp_dir(), 'nxws');
        file_put_contents($this->portFile, '');
        $this->process = proc_open([PHP_BINARY, __DIR__ . '/../Support/ws_upstream_server.php', $this->portFile], [], $pipes);
        for ($i = 0; $i < 100 && trim((string) file_get_contents($this->portFile)) === ''; $i++) {
            usleep(20_000);
        }

        return (int) trim((string) file_get_contents($this->portFile));
    }

    private function proxy(int $upstreamPort, int $maxPerIp = 10, int $maxPerUser = 3): WebSocketProxy
    {
        return new WebSocketProxy(function (string $token) use ($upstreamPort): ?array {
            if (!in_array($token, [str_repeat('ab', 32), str_repeat('ef', 32)], true) || in_array($token, $this->redeemed, true)) {
                return null;
            }
            $this->redeemed[] = $token;

            return [
                'service_id' => 7, 'user_id' => 3, 'scheme' => 'ws', 'host' => '127.0.0.1', 'ip' => '127.0.0.1', 'port' => $upstreamPort,
                'path' => '/api2/json/nodes/pve1/qemu/101/vncwebsocket?port=5900&vncticket=PVEVNC%3ATICKET',
                'headers' => ['Authorization' => 'PVEAPIToken=nebrix@pve!panel=secret-token', 'Accept' => 'application/json'],
                'verify_tls' => true,
            ];
        }, new NullLogger(), self::ORIGIN, 100, 14400, 3, [], $maxPerIp, $maxPerUser);
    }

    /**
     * @return resource
     */
    private function client(string $address, string $token, string $origin = self::ORIGIN, bool $upgrade = true)
    {
        $socket = stream_socket_client('tcp://' . $address, $errno, $error, 2);
        self::assertNotFalse($socket, $error);
        $request = "GET /console/ws?token={$token} HTTP/1.1\r\nHost: panel.example.test\r\n"
            . ($upgrade ? "Upgrade: websocket\r\nConnection: Upgrade\r\n" : '')
            . "Sec-WebSocket-Key: " . base64_encode(random_bytes(16)) . "\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Protocol: binary\r\nOrigin: {$origin}\r\n\r\n";
        fwrite($socket, $request);
        stream_set_blocking($socket, false);

        return $socket;
    }

    /**
     * @param resource $socket
     */
    private function readUntil(WebSocketProxy $proxy, $socket, string $needle): string
    {
        $received = '';
        for ($i = 0; $i < 200 && !str_contains($received, $needle); $i++) {
            $proxy->tick(0.02);
            $received .= (string) fread($socket, 65536);
            if (feof($socket)) {
                break;
            }
        }

        return $received;
    }

    public function testRelaysFramesBetweenBrowserAndHypervisor(): void
    {
        $proxy = $this->proxy($this->upstreamPort());
        $address = $proxy->listen('127.0.0.1:0');
        $client = $this->client($address, str_repeat('ab', 32));

        $handshake = $this->readUntil($proxy, $client, 'RFB 003.008');
        self::assertStringStartsWith("HTTP/1.1 101 Switching Protocols\r\n", $handshake);
        self::assertStringContainsString("Sec-WebSocket-Protocol: binary\r\n", $handshake);
        self::assertStringContainsString('RFB 003.008', $handshake, 'Bytes sent by the upstream together with its handshake are relayed.');

        // A masked client frame passes through unchanged; the reply comes back.
        $mask = random_bytes(4);
        $payload = 'hello';
        $masked = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }
        fwrite($client, chr(0x82) . chr(0x80 | strlen($payload)) . $mask . $masked);
        $reply = $this->readUntil($proxy, $client, 'echo:hello');
        self::assertSame(chr(0x82) . chr(10) . 'echo:hello', $reply);
        self::assertSame(1, $proxy->connectionCount());

        fclose($client);
        for ($i = 0; $i < 20 && $proxy->connectionCount() > 0; $i++) {
            $proxy->tick(0.02);
        }
        self::assertSame(0, $proxy->connectionCount());
        $proxy->shutdown();
    }

    public function testRejectsBadOriginTokenAndRequests(): void
    {
        $proxy = $this->proxy(9);
        $address = $proxy->listen('127.0.0.1:0');

        $cases = [
            [$this->client($address, str_repeat('ab', 32), 'https://evil.example'), '403'],
            [$this->client($address, str_repeat('cd', 32)), '403'],
            [$this->client($address, str_repeat('ab', 32), self::ORIGIN, false), '400'],
        ];
        foreach ($cases as [$socket, $status]) {
            self::assertStringStartsWith('HTTP/1.1 ' . $status, $this->readUntil($proxy, $socket, "\r\n\r\n"));
        }
        self::assertSame([], $this->redeemed, 'Tokens are not redeemed for requests from foreign origins or without upgrade.');
        $proxy->shutdown();
    }

    public function testLimitsOpenConsolesPerUser(): void
    {
        $proxy = $this->proxy($this->upstreamPort(), 10, 1);
        $address = $proxy->listen('127.0.0.1:0');
        $first = $this->client($address, str_repeat('ab', 32));
        self::assertStringStartsWith('HTTP/1.1 101', $this->readUntil($proxy, $first, 'RFB 003.008'));

        $second = $this->client($address, str_repeat('ef', 32));
        self::assertStringStartsWith('HTTP/1.1 429', $this->readUntil($proxy, $second, "\r\n\r\n"));
        self::assertSame(1, $proxy->connectionCount());
        $proxy->shutdown();
    }

    public function testLimitsConnectionsPerClientIp(): void
    {
        $proxy = $this->proxy(9, 2);
        $address = $proxy->listen('127.0.0.1:0');
        $sockets = [];
        for ($i = 0; $i < 3; $i++) {
            $socket = stream_socket_client('tcp://' . $address, $errno, $error, 2);
            self::assertNotFalse($socket, $error);
            stream_set_blocking($socket, false);
            $sockets[] = $socket;
            $proxy->tick(0.05);
        }

        self::assertSame(2, $proxy->connectionCount(), 'Idle handshakes from one address are capped.');
        self::assertStringStartsWith('HTTP/1.1 503', $this->readUntil($proxy, $sockets[2], "\r\n\r\n"));
        $proxy->shutdown();
    }
}
