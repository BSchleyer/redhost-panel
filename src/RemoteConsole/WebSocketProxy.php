<?php

declare(strict_types=1);

namespace Nebrix\RemoteConsole;

use Nebrix\Http\IpRange;
use Nebrix\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * Relays a browser console (noVNC) WebSocket to the hypervisor's console
 * WebSocket.
 *
 * Browsers cannot send the API token the hypervisor requires, so the panel
 * hands out single-use tokens (ConsoleSessions) and this process performs
 * the upstream handshake with the stored credentials. After both handshakes
 * the WebSocket frames are relayed unchanged: client frames stay masked
 * (valid towards the upstream server), server frames stay unmasked (valid
 * towards the browser).
 *
 * Checks: GET + WebSocket upgrade headers, Origin equal to the panel origin,
 * token redeemed exactly once, upstream address pinned and TLS verified per
 * integration setting, upstream Sec-WebSocket-Accept verified. Limits:
 * connection count, concurrent handshakes, connections per client IP (the
 * X-Forwarded-For address when the peer is a trusted proxy), relays per
 * user, header size, handshake timeout, connection lifetime and buffered
 * bytes per direction. A side is not read while the opposite direction has
 * more than HIGH_WATER bytes queued (backpressure).
 */
final class WebSocketProxy
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    private const MAX_HEADER_BYTES = 8192;
    private const HANDSHAKE_TIMEOUT = 10;
    private const CHUNK = 65536;
    /** Bytes read from one socket per event-loop pass. */
    private const MAX_READ = 262144;
    /** Reading pauses while the opposite buffer holds more than this. */
    private const HIGH_WATER = 524288;
    /** Safety net; with backpressure a buffer stays below HIGH_WATER + MAX_READ. */
    private const MAX_BUFFER = 2097152;

    /** @var resource|null */
    private $server = null;

    /**
     * @var array<int, array{client: resource, upstream: resource|null, state: string, in: string, to_client: string, to_upstream: string, since: int, service: int, user: int, peer: string, ip: string}>
     */
    private array $connections = [];

    /** @var array<int, array{0: int, 1: string}> socket id => [connection id, side] */
    private array $sockets = [];

    private int $nextId = 1;

    /**
     * @param \Closure(string): ?array{service_id: int, user_id: int, scheme: string, host: string, ip: string, port: int, path: string, headers: array<string, string>, verify_tls: bool} $redeem
     * @param list<string> $trustedProxies    peers whose X-Forwarded-For is used for the per-IP limit
     * @param int          $maxPerIp          connections per client IP (0 = unlimited)
     * @param int          $maxPerUser        console relays per panel user (0 = unlimited)
     */
    public function __construct(
        private readonly \Closure $redeem,
        private readonly LoggerInterface $logger,
        private readonly string $allowedOrigin,
        private readonly int $maxConnections = 100,
        private readonly int $maxLifetime = 14400,
        private readonly int $connectTimeout = 3,
        private readonly array $trustedProxies = [],
        private readonly int $maxPerIp = 10,
        private readonly int $maxPerUser = 3,
        private readonly int $maxHandshakes = 32,
    ) {
    }

    /**
     * @return string the bound address (useful with port 0)
     */
    public function listen(string $address): string
    {
        $server = @stream_socket_server('tcp://' . $address, $errno, $error);
        if ($server === false) {
            throw new \RuntimeException(sprintf('Cannot listen on %s: %s', $address, $error));
        }
        stream_set_blocking($server, false);
        $this->server = $server;

        return (string) stream_socket_get_name($server, false);
    }

    /**
     * @param (\Closure(): bool)|null $shouldStop
     */
    public function run(?\Closure $shouldStop = null): void
    {
        while ($shouldStop === null || !$shouldStop()) {
            $this->tick(1.0);
        }
        $this->shutdown();
    }

    public function connectionCount(): int
    {
        return count($this->connections);
    }

    public function tick(float $timeout): void
    {
        // Data already decrypted by the TLS layer is not signalled by select().
        foreach ($this->connections as $id => $connection) {
            if ($connection['upstream'] !== null && strlen($connection['to_client']) < self::HIGH_WATER && stream_get_meta_data($connection['upstream'])['unread_bytes'] > 0) {
                $this->readFrom($connection['upstream']);
                $timeout = 0.0;
            }
        }

        $read = $this->server !== null ? [$this->server] : [];
        $write = [];
        foreach ($this->connections as $connection) {
            // Backpressure: stop reading a side while the other cannot keep up.
            if (strlen($connection['to_upstream']) < self::HIGH_WATER) {
                $read[] = $connection['client'];
            }
            if ($connection['upstream'] !== null) {
                if (strlen($connection['to_client']) < self::HIGH_WATER) {
                    $read[] = $connection['upstream'];
                }
                if ($connection['to_upstream'] !== '') {
                    $write[] = $connection['upstream'];
                }
            }
            if ($connection['to_client'] !== '') {
                $write[] = $connection['client'];
            }
        }
        if ($read === [] && $write === []) {
            usleep((int) ($timeout * 1_000_000));

            return;
        }

        $except = null;
        $seconds = (int) $timeout;
        $changed = @stream_select($read, $write, $except, $seconds, (int) (($timeout - $seconds) * 1_000_000));
        if ($changed === false) {
            return;
        }

        foreach ($read as $socket) {
            if ($socket === $this->server) {
                $this->accept();
            } else {
                $this->readFrom($socket);
            }
        }
        foreach ($write as $socket) {
            $this->writeTo($socket);
        }
        $this->expire();
    }

    public function shutdown(): void
    {
        foreach (array_keys($this->connections) as $id) {
            $this->close($id, 'proxy shutdown');
        }
        if ($this->server !== null) {
            fclose($this->server);
            $this->server = null;
        }
    }

    private function accept(): void
    {
        if ($this->server === null) {
            return;
        }
        $client = @stream_socket_accept($this->server, 0, $peer);
        if ($client === false) {
            return;
        }
        $ip = self::peerIp((string) $peer);
        $handshakes = count(array_filter($this->connections, static fn (array $c): bool => $c['state'] === 'handshake'));
        if (count($this->connections) >= $this->maxConnections
            || $handshakes >= $this->maxHandshakes
            || (!$this->isTrustedProxy($ip) && $this->limitReached('ip', $ip, $this->maxPerIp))) {
            @fwrite($client, "HTTP/1.1 503 Service Unavailable\r\nConnection: close\r\nContent-Length: 0\r\n\r\n");
            fclose($client);

            return;
        }
        stream_set_blocking($client, false);
        $id = $this->nextId++;
        $this->connections[$id] = ['client' => $client, 'upstream' => null, 'state' => 'handshake', 'in' => '', 'to_client' => '', 'to_upstream' => '', 'since' => time(), 'service' => 0, 'user' => 0, 'peer' => (string) $peer, 'ip' => $ip];
        $this->sockets[get_resource_id($client)] = [$id, 'client'];
    }

    /**
     * @param resource $socket
     */
    private function readFrom($socket): void
    {
        [$id, $side] = $this->sockets[get_resource_id($socket)] ?? [0, ''];
        if (!isset($this->connections[$id])) {
            return;
        }

        $data = $this->readAvailable($socket);
        if ($data === null) {
            $this->close($id, $side . ' closed the connection');

            return;
        }

        if ($side === 'upstream') {
            $this->connections[$id]['to_client'] .= $data;
        } elseif ($this->connections[$id]['state'] === 'handshake') {
            $this->connections[$id]['in'] .= $data;
            if (strlen($this->connections[$id]['in']) > self::MAX_HEADER_BYTES) {
                $this->reject($id, 431, 'Request Header Fields Too Large');

                return;
            }
            if (str_contains($this->connections[$id]['in'], "\r\n\r\n")) {
                $this->handshake($id);
            }
        } else {
            $this->connections[$id]['to_upstream'] .= $data;
        }

        if (isset($this->connections[$id]) && (strlen($this->connections[$id]['to_client']) > self::MAX_BUFFER || strlen($this->connections[$id]['to_upstream']) > self::MAX_BUFFER)) {
            $this->close($id, 'buffer limit exceeded');
        }
    }

    /**
     * @param resource $socket
     * @return string|null null when the peer closed the connection
     */
    private function readAvailable($socket): ?string
    {
        $buffer = '';
        while (strlen($buffer) < self::MAX_READ) {
            $chunk = @fread($socket, self::CHUNK);
            if ($chunk === false || $chunk === '') {
                if (feof($socket)) {
                    return $buffer === '' ? null : $buffer;
                }
                break;
            }
            $buffer .= $chunk;
            if (stream_get_meta_data($socket)['unread_bytes'] === 0 && strlen($chunk) < self::CHUNK) {
                break;
            }
        }

        return $buffer;
    }

    /**
     * @param resource $socket
     */
    private function writeTo($socket): void
    {
        [$id, $side] = $this->sockets[get_resource_id($socket)] ?? [0, ''];
        if (!isset($this->connections[$id])) {
            return;
        }
        $key = $side === 'client' ? 'to_client' : 'to_upstream';
        if ($this->connections[$id][$key] === '') {
            return;
        }
        $written = @fwrite($socket, $this->connections[$id][$key]);
        if ($written === false) {
            $this->close($id, 'write to ' . $side . ' failed');

            return;
        }
        $this->connections[$id][$key] = (string) substr($this->connections[$id][$key], $written);
    }

    private function handshake(int $id): void
    {
        [$head, $rest] = explode("\r\n\r\n", $this->connections[$id]['in'], 2);
        $this->connections[$id]['in'] = '';
        $lines = explode("\r\n", $head);
        if (preg_match('#^GET (/\S*) HTTP/1\.1$#', (string) array_shift($lines), $match) !== 1) {
            $this->reject($id, 400, 'Bad Request');

            return;
        }
        $headers = [];
        foreach ($lines as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        $key = $headers['sec-websocket-key'] ?? '';
        $decodedKey = base64_decode($key, true);
        if (strtolower($headers['upgrade'] ?? '') !== 'websocket'
            || !str_contains(strtolower($headers['connection'] ?? ''), 'upgrade')
            || ($headers['sec-websocket-version'] ?? '') !== '13'
            || $decodedKey === false || strlen($decodedKey) !== 16
            || $rest !== '') {
            $this->reject($id, 400, 'Bad Request');

            return;
        }
        if ($this->allowedOrigin !== '' && ($headers['origin'] ?? '') !== $this->allowedOrigin) {
            $this->reject($id, 403, 'Forbidden');

            return;
        }
        if ($this->isTrustedProxy($this->connections[$id]['ip'])) {
            $server = ['REMOTE_ADDR' => $this->connections[$id]['ip']];
            if (isset($headers['x-forwarded-for'])) {
                $server['HTTP_X_FORWARDED_FOR'] = $headers['x-forwarded-for'];
            }
            $this->connections[$id]['ip'] = (new Request('GET', '/', [], [], [], [], $server, '', $this->trustedProxies))->clientIp();
            if ($this->limitReached('ip', $this->connections[$id]['ip'], $this->maxPerIp, $id)) {
                $this->reject($id, 429, 'Too Many Requests');

                return;
            }
        }

        parse_str((string) parse_url($match[1], PHP_URL_QUERY), $query);
        $target = ($this->redeem)(is_string($query['token'] ?? null) ? $query['token'] : '');
        if ($target === null) {
            $this->reject($id, 403, 'Forbidden');

            return;
        }
        if ($this->limitReached('user', $target['user_id'], $this->maxPerUser, $id)) {
            $this->logger->notice('Console session refused: too many open consoles', ['user' => $target['user_id'], 'service' => $target['service_id']]);
            $this->reject($id, 429, 'Too Many Requests');

            return;
        }

        $offered = array_map('trim', explode(',', $headers['sec-websocket-protocol'] ?? ''));
        $upstream = $this->connectUpstream($target);
        if ($upstream === null) {
            $this->logger->warning('Console upstream connection failed', ['service' => $target['service_id']]);
            $this->reject($id, 502, 'Bad Gateway');

            return;
        }
        [$socket, $leftover, $protocol] = $upstream;

        $response = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            . 'Sec-WebSocket-Accept: ' . base64_encode(sha1($key . self::GUID, true)) . "\r\n";
        if ($protocol !== '' && in_array($protocol, $offered, true)) {
            $response .= 'Sec-WebSocket-Protocol: ' . $protocol . "\r\n";
        }

        $this->connections[$id]['upstream'] = $socket;
        $this->connections[$id]['state'] = 'relay';
        $this->connections[$id]['since'] = time();
        $this->connections[$id]['service'] = $target['service_id'];
        $this->connections[$id]['user'] = $target['user_id'];
        $this->connections[$id]['to_client'] = $response . "\r\n" . $leftover;
        $this->sockets[get_resource_id($socket)] = [$id, 'upstream'];
        $this->logger->info('Console session started', ['service' => $target['service_id'], 'user' => $target['user_id'], 'ip' => $this->connections[$id]['ip']]);
    }

    /**
     * @param array{scheme: string, host: string, ip: string, port: int, path: string, headers: array<string, string>, verify_tls: bool} $target
     * @return array{0: resource, 1: string, 2: string}|null socket, bytes after the handshake, selected protocol
     */
    private function connectUpstream(array $target): ?array
    {
        $verify = $target['verify_tls'];
        $context = stream_context_create(['ssl' => [
            'peer_name' => $target['host'],
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
            'SNI_enabled' => true,
        ]]);
        $address = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
        $transport = $target['scheme'] === 'wss' ? 'tls' : 'tcp';
        $socket = @stream_socket_client(sprintf('%s://%s:%d', $transport, $address, $target['port']), $errno, $error, $this->connectTimeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            return null;
        }
        stream_set_timeout($socket, $this->connectTimeout);

        $key = base64_encode(random_bytes(16));
        $hostHeader = str_contains($target['host'], ':') && !str_starts_with($target['host'], '[') ? '[' . $target['host'] . ']' : $target['host'];
        $request = sprintf("GET %s HTTP/1.1\r\nHost: %s:%d\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: %s\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Protocol: binary\r\n", $target['path'], $hostHeader, $target['port'], $key);
        foreach ($target['headers'] as $name => $value) {
            if (preg_match('/^[A-Za-z0-9-]+$/', $name) === 1 && !preg_match('/[\r\n]/', $value) && !in_array(strtolower($name), ['host', 'upgrade', 'connection', 'content-type', 'accept'], true)) {
                $request .= $name . ': ' . $value . "\r\n";
            }
        }
        if (@fwrite($socket, $request . "\r\n") === false) {
            fclose($socket);

            return null;
        }

        $response = '';
        $deadline = microtime(true) + $this->connectTimeout;
        while (!str_contains($response, "\r\n\r\n") && strlen($response) < self::MAX_HEADER_BYTES && microtime(true) < $deadline) {
            $chunk = @fread($socket, 4096);
            if ($chunk === false || ($chunk === '' && feof($socket))) {
                break;
            }
            $response .= $chunk;
        }
        if (!str_contains($response, "\r\n\r\n")) {
            fclose($socket);

            return null;
        }

        [$head, $leftover] = explode("\r\n\r\n", $response, 2);
        $lines = explode("\r\n", $head);
        $headers = [];
        foreach (array_slice($lines, 1) as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }
        if (preg_match('#^HTTP/1\.1 101 #', $lines[0] . ' ') !== 1
            || ($headers['sec-websocket-accept'] ?? '') !== base64_encode(sha1($key . self::GUID, true))) {
            fclose($socket);

            return null;
        }

        stream_set_blocking($socket, false);

        return [$socket, $leftover, $headers['sec-websocket-protocol'] ?? ''];
    }

    /**
     * "192.0.2.1:5000" / "[2001:db8::1]:5000" → address without port.
     */
    private static function peerIp(string $peer): string
    {
        if (str_starts_with($peer, '[')) {
            $end = strpos($peer, ']');

            return $end === false ? $peer : substr($peer, 1, $end - 1);
        }
        $colon = strrpos($peer, ':');
        if ($colon !== false && substr_count($peer, ':') === 1) {
            return substr($peer, 0, $colon);
        }

        return $peer;
    }

    private function isTrustedProxy(string $ip): bool
    {
        foreach ($this->trustedProxies as $proxy) {
            if (IpRange::contains($proxy, $ip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param 'ip'|'user' $field
     */
    private function limitReached(string $field, int|string $value, int $limit, int $exceptId = 0): bool
    {
        if ($limit <= 0) {
            return false;
        }
        $count = 0;
        foreach ($this->connections as $id => $connection) {
            if ($id !== $exceptId && $connection[$field] === $value && ($field === 'ip' || $connection['state'] === 'relay')) {
                $count++;
            }
        }

        return $count >= $limit;
    }

    private function reject(int $id, int $status, string $reason): void
    {
        if (isset($this->connections[$id])) {
            @fwrite($this->connections[$id]['client'], sprintf("HTTP/1.1 %d %s\r\nConnection: close\r\nContent-Length: 0\r\n\r\n", $status, $reason));
        }
        $this->close($id, 'rejected with ' . $status);
    }

    private function expire(): void
    {
        $now = time();
        foreach ($this->connections as $id => $connection) {
            if ($connection['state'] === 'handshake' && $now - $connection['since'] > self::HANDSHAKE_TIMEOUT) {
                $this->reject($id, 408, 'Request Timeout');
            } elseif ($connection['state'] === 'relay' && $now - $connection['since'] > $this->maxLifetime) {
                $this->close($id, 'maximum session duration reached');
            }
        }
    }

    private function close(int $id, string $reason): void
    {
        $connection = $this->connections[$id] ?? null;
        if ($connection === null) {
            return;
        }
        unset($this->sockets[get_resource_id($connection['client'])]);
        @fclose($connection['client']);
        if ($connection['upstream'] !== null) {
            unset($this->sockets[get_resource_id($connection['upstream'])]);
            @fclose($connection['upstream']);
        }
        unset($this->connections[$id]);
        if ($connection['state'] === 'relay') {
            $this->logger->info('Console session ended', ['service' => $connection['service'], 'reason' => $reason]);
        }
    }
}
