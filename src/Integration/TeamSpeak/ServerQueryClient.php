<?php

declare(strict_types=1);

namespace Nebrix\Integration\TeamSpeak;

use Nebrix\Integration\HealthResult;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationLogger;

/**
 * TeamSpeak 3 ServerQuery client.
 *
 * Commands are built only from validated names and keys; every value is
 * escaped according to the ServerQuery specification, so user-controlled
 * values (server names) cannot inject additional commands or parameters.
 */
class ServerQueryClient
{
    private bool $loggedIn = false;

    private ?int $selectedServer = null;

    public function __construct(
        private readonly Transport $transport,
        private readonly string $user,
        #[\SensitiveParameter] private readonly string $password,
        private readonly IntegrationLogger $logger,
        private readonly int $integrationId,
    ) {
    }

    public static function escape(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\', '/' => '\\/', ' ' => '\\s', '|' => '\\p',
            "\x07" => '\\a', "\x08" => '\\b', "\f" => '\\f', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t', "\v" => '\\v',
        ]);
    }

    public static function unescape(string $value): string
    {
        return strtr($value, [
            '\\\\' => '\\', '\\/' => '/', '\\s' => ' ', '\\p' => '|',
            '\\a' => "\x07", '\\b' => "\x08", '\\f' => "\f", '\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\v' => "\v",
        ]);
    }

    /**
     * @param array<string, scalar|null> $params
     * @param list<string> $flags e.g. ['-virtual']
     */
    public static function buildCommand(string $command, array $params = [], array $flags = []): string
    {
        if (preg_match('/^[a-z_]+$/', $command) !== 1) {
            throw new IntegrationException('Invalid ServerQuery command.');
        }

        $parts = [$command];
        foreach ($params as $key => $value) {
            if (preg_match('/^[a-z0-9_]+$/', $key) !== 1) {
                throw new IntegrationException('Invalid ServerQuery parameter.');
            }
            if ($value === null) {
                continue;
            }
            $parts[] = $key . '=' . self::escape(is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }
        foreach ($flags as $flag) {
            if (preg_match('/^-[a-z_]+$/', $flag) !== 1) {
                throw new IntegrationException('Invalid ServerQuery option.');
            }
            $parts[] = $flag;
        }

        return implode(' ', $parts);
    }

    /**
     * Sends a command and returns the parsed records.
     *
     * @param array<string, scalar|null> $params
     * @param list<string> $flags
     * @return list<array<string, string>>
     */
    public function command(string $command, array $params = [], array $flags = []): array
    {
        $this->ensureLoggedIn();

        return $this->send($command, $params, $flags);
    }

    /**
     * @param array<string, scalar|null> $params
     * @param list<string> $flags
     * @return list<array<string, string>>
     */
    private function send(string $command, array $params = [], array $flags = []): array
    {
        $line = self::buildCommand($command, $params, $flags);
        $started = hrtime(true);
        $this->transport->writeLine($line);

        $data = [];
        while (true) {
            $response = $this->transport->readLine();
            if ($response === '') {
                continue;
            }
            if (str_starts_with($response, 'error ')) {
                $error = self::parseRecord(substr($response, 6));
                $id = (int) ($error['id'] ?? -1);
                $duration = (int) ((hrtime(true) - $started) / 1_000_000);
                if ($id !== 0) {
                    $message = sprintf('ServerQuery error %d: %s', $id, $error['msg'] ?? 'unknown');
                    if (isset($error['extra_msg'])) {
                        $message .= ' (' . $error['extra_msg'] . ')';
                    }
                    $this->logger->log($this->integrationId, 'warning', $command, $message, [], $duration);

                    throw new ServerQueryException($message, $id);
                }
                $this->logger->log($this->integrationId, 'debug', $command, 'ok', [], $duration);

                return $data;
            }

            foreach (explode('|', $response) as $record) {
                $data[] = self::parseRecord($record);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public static function parseRecord(string $record): array
    {
        $result = [];
        foreach (explode(' ', trim($record)) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $result[$key] = self::unescape($value);
        }

        return $result;
    }

    private function ensureLoggedIn(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $this->transport->connect();
        $this->send('login', ['client_login_name' => $this->user, 'client_login_password' => $this->password]);
        $this->loggedIn = true;
    }

    public function useServer(int $serverId): void
    {
        if ($this->selectedServer === $serverId) {
            return;
        }
        $this->command('use', ['sid' => $serverId], ['-virtual']);
        $this->selectedServer = $serverId;
    }

    public function healthCheck(): HealthResult
    {
        try {
            $version = $this->command('version')[0] ?? [];
            $servers = $this->listServers();
        } catch (IntegrationException $exception) {
            return HealthResult::down($exception->getMessage());
        } finally {
            $this->disconnect();
        }

        return HealthResult::healthy(sprintf('TeamSpeak %s, %d virtual server(s)', $version['version'] ?? 'unknown', count($servers)));
    }

    /**
     * @return list<array<string, string>>
     */
    public function listServers(): array
    {
        return $this->command('serverlist', [], ['-uid']);
    }

    /**
     * @return array{sid: int, port: int, token: string}
     */
    public function createServer(string $name, int $slots, int $port): array
    {
        $result = $this->command('servercreate', [
            'virtualserver_name' => mb_substr($name, 0, 64),
            'virtualserver_maxclients' => $slots,
            'virtualserver_port' => $port,
            'virtualserver_autostart' => 1,
        ])[0] ?? [];

        if (!isset($result['sid']) || !ctype_digit($result['sid'])) {
            throw new IntegrationException('TeamSpeak did not return a server ID.');
        }

        return ['sid' => (int) $result['sid'], 'port' => (int) ($result['virtualserver_port'] ?? $port), 'token' => (string) ($result['token'] ?? '')];
    }

    public function startServer(int $serverId): void
    {
        $this->command('serverstart', ['sid' => $serverId]);
    }

    public function stopServer(int $serverId): void
    {
        if ($this->selectedServer === $serverId) {
            $this->command('use', ['sid' => 0]);
            $this->selectedServer = null;
        }
        $this->command('serverstop', ['sid' => $serverId]);
    }

    public function deleteServer(int $serverId): void
    {
        $this->command('serverdelete', ['sid' => $serverId]);
    }

    /**
     * @param array<string, scalar> $properties virtualserver_* properties
     */
    public function editServer(int $serverId, array $properties): void
    {
        foreach (array_keys($properties) as $key) {
            if (!str_starts_with($key, 'virtualserver_')) {
                throw new IntegrationException('Only virtual server properties can be edited.');
            }
        }
        $this->useServer($serverId);
        $this->command('serveredit', $properties);
    }

    /**
     * @return array<string, string>
     */
    public function serverInfo(int $serverId): array
    {
        $this->useServer($serverId);

        return $this->command('serverinfo')[0] ?? [];
    }

    /** Creates a privilege key for the "Server Admin" group (or another group). */
    public function createPrivilegeKey(int $serverId, string $description, string $groupName = 'Server Admin'): string
    {
        $this->useServer($serverId);
        $groupId = null;
        foreach ($this->command('servergrouplist') as $group) {
            // type 1 = regular server group
            if (($group['name'] ?? '') === $groupName && ($group['type'] ?? '') === '1') {
                $groupId = (int) $group['sgid'];
                break;
            }
        }
        if ($groupId === null) {
            throw new IntegrationException(sprintf('Server group "%s" not found.', $groupName));
        }

        $result = $this->command('privilegekeyadd', ['tokentype' => 0, 'tokenid1' => $groupId, 'tokenid2' => 0, 'tokendescription' => mb_substr($description, 0, 100)])[0] ?? [];
        if (($result['token'] ?? '') === '') {
            throw new IntegrationException('TeamSpeak did not return a privilege key.');
        }

        return $result['token'];
    }

    /**
     * @return list<int> ports used by existing virtual servers
     */
    public function usedPorts(): array
    {
        return array_values(array_map(static fn (array $server): int => (int) ($server['virtualserver_port'] ?? 0), $this->listServers()));
    }

    public function disconnect(): void
    {
        if ($this->loggedIn) {
            $this->transport->close();
        }
        $this->loggedIn = false;
        $this->selectedServer = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
