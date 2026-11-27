<?php

declare(strict_types=1);

namespace Nebrix\Console\Command;

use Nebrix\Console\Command;
use Nebrix\Console\Input;
use Nebrix\Console\Output;
use Nebrix\Core\Config;
use Nebrix\Database\Connection;
use Nebrix\RemoteConsole\ConsoleSessions;
use Nebrix\RemoteConsole\WebSocketProxy;
use Psr\Log\LoggerInterface;

/**
 * bin/nebrix console:proxy [--listen=127.0.0.1:8081]
 *
 * WebSocket relay for server consoles. Run it behind the TLS-terminating
 * reverse proxy, which forwards CONSOLE_WS_URL (default /console/ws) to it.
 */
final class ConsoleProxyCommand implements Command
{
    private bool $stop = false;

    public function __construct(
        private readonly ConsoleSessions $sessions,
        private readonly Connection $db,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function description(): string
    {
        return 'Run the WebSocket proxy for server consoles [--listen=127.0.0.1:8081]';
    }

    public function run(Input $input, Output $output): int
    {
        // Long-running process: an explicit ceiling instead of the unlimited
        // CLI default. Buffers are bounded per connection (see WebSocketProxy).
        ini_set('memory_limit', $this->config->string('console.memory_limit', '512M'));
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->stop = true);
            pcntl_signal(SIGINT, fn () => $this->stop = true);
        }

        $url = parse_url($this->config->string('app.url'));
        $origin = sprintf('%s://%s%s', $url['scheme'] ?? 'https', $url['host'] ?? 'localhost', isset($url['port']) ? ':' . $url['port'] : '');

        $proxy = new WebSocketProxy(
            function (string $token): ?array {
                $this->db->ensureAlive();

                return $this->sessions->redeem($token);
            },
            $this->logger,
            $origin,
            max(1, $this->config->int('console.max_connections', 100)),
            max(60, $this->config->int('console.max_lifetime', 14400)),
            3,
            array_values(array_filter($this->config->array('app.trusted_proxies'), 'is_string')),
            max(0, $this->config->int('console.max_per_ip', 10)),
            max(0, $this->config->int('console.max_per_user', 3)),
        );

        $address = $proxy->listen((string) ($input->option('listen') ?? $this->config->string('console.listen', '127.0.0.1:8081')));
        $output->info(sprintf('Console proxy listening on %s (origin %s).', $address, $origin));
        $proxy->run(fn (): bool => $this->stop);

        return 0;
    }
}
