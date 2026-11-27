<?php

declare(strict_types=1);

namespace Nebrix\Integration\TeamSpeak;

use Nebrix\Integration\IntegrationException;

/**
 * Raw TCP ServerQuery transport (default port 10011).
 *
 * ServerQuery over raw TCP is not encrypted. Operators must restrict the
 * query port to the panel's address and connect over a private network or
 * VPN (documented in INTEGRATIONS.md).
 */
final class SocketTransport implements Transport
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $ip,
        private readonly int $port,
        private readonly int $timeout = 10,
    ) {
    }

    public function connect(): void
    {
        $address = sprintf(str_contains($this->ip, ':') ? 'tcp://[%s]:%d' : 'tcp://%s:%d', $this->ip, $this->port);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, $this->timeout);
        if ($socket === false) {
            throw new IntegrationException(sprintf('Could not connect to ServerQuery (%s).', $errstr !== '' ? $errstr : 'error ' . $errno));
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        $banner = $this->readLine();
        if ($banner !== 'TS3') {
            $this->close();
            throw new IntegrationException('The remote service is not a TeamSpeak 3 ServerQuery interface.');
        }
        // Second banner line ("Welcome to the TeamSpeak 3 ServerQuery interface …").
        $this->readLine();
    }

    public function writeLine(string $line): void
    {
        if ($this->socket === null) {
            throw new IntegrationException('ServerQuery is not connected.');
        }
        if (@fwrite($this->socket, $line . "\n") === false) {
            throw new IntegrationException('Writing to ServerQuery failed.');
        }
    }

    public function readLine(): string
    {
        if ($this->socket === null) {
            throw new IntegrationException('ServerQuery is not connected.');
        }

        $line = @fgets($this->socket, 1_048_576);
        if ($line === false) {
            $meta = stream_get_meta_data($this->socket);
            throw new IntegrationException($meta['timed_out'] ? 'ServerQuery timed out.' : 'ServerQuery connection closed.');
        }

        // ServerQuery terminates lines with "\n\r".
        return trim($line, "\r\n");
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fwrite($this->socket, "quit\n");
            fclose($this->socket);
            $this->socket = null;
        }
    }
}
