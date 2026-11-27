<?php

declare(strict_types=1);

namespace Nebrix\Tests\Support;

use Nebrix\Integration\TeamSpeak\ServerQueryClient;
use Nebrix\Integration\TeamSpeak\Transport;

/**
 * In-memory TeamSpeak 3 ServerQuery server implementing the commands used by
 * Nebrix, including escaping, so the client and provisioner can be tested
 * against realistic protocol responses.
 */
final class FakeServerQuery implements Transport
{
    /** @var list<string> */
    public array $commands = [];

    /** @var array<int, array{port: int, name: string, slots: int, status: string, autostart: int}> */
    public array $servers = [];

    /** @var list<string> */
    private array $output = [];

    public bool $connected = false;

    public int $nextSid = 1;

    private ?int $selected = null;

    public function __construct(private readonly string $password = 'secret')
    {
    }

    public function connect(): void
    {
        $this->connected = true;
        $this->output = [];
    }

    public function writeLine(string $line): void
    {
        $this->commands[] = $line;
        $parts = explode(' ', $line);
        $command = array_shift($parts);
        $params = [];
        foreach ($parts as $part) {
            if (str_starts_with($part, '-')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $params[$key] = ServerQueryClient::unescape($value);
        }

        $this->handle((string) $command, $params);
    }

    /**
     * @param array<string, string> $params
     */
    private function handle(string $command, array $params): void
    {
        switch ($command) {
            case 'login':
                $this->respond($params['client_login_password'] === $this->password ? 0 : 520, $params['client_login_password'] === $this->password ? 'ok' : 'invalid loginname or password');
                break;
            case 'version':
                $this->output[] = 'version=3.13.7 build=1655727713 platform=Linux';
                $this->respond(0);
                break;
            case 'serverlist':
                $records = [];
                foreach ($this->servers as $sid => $server) {
                    $records[] = sprintf(
                        'virtualserver_id=%d virtualserver_port=%d virtualserver_status=%s virtualserver_clientsonline=0 virtualserver_maxclients=%d virtualserver_name=%s virtualserver_uptime=10',
                        $sid, $server['port'], $server['status'], $server['slots'], ServerQueryClient::escape($server['name']),
                    );
                }
                if ($records !== []) {
                    $this->output[] = implode('|', $records);
                }
                $this->respond(0);
                break;
            case 'servercreate':
                foreach ($this->servers as $server) {
                    if ($server['port'] === (int) $params['virtualserver_port']) {
                        $this->respond(1028, 'port already in use');

                        return;
                    }
                }
                $sid = $this->nextSid++;
                $this->servers[$sid] = ['port' => (int) $params['virtualserver_port'], 'name' => $params['virtualserver_name'], 'slots' => (int) $params['virtualserver_maxclients'], 'status' => 'online', 'autostart' => 1];
                $this->output[] = sprintf('sid=%d virtualserver_port=%d token=TOKEN%d\/abc', $sid, $params['virtualserver_port'], $sid);
                $this->respond(0);
                break;
            case 'use':
                $sid = (int) $params['sid'];
                if ($sid !== 0 && !isset($this->servers[$sid])) {
                    $this->respond(1024, 'invalid serverID');

                    return;
                }
                $this->selected = $sid === 0 ? null : $sid;
                $this->respond(0);
                break;
            case 'serverstop':
                $sid = (int) $params['sid'];
                if (!isset($this->servers[$sid])) {
                    $this->respond(1024, 'invalid serverID');
                } elseif ($this->servers[$sid]['status'] !== 'online') {
                    $this->respond(1033, 'server is not running');
                } else {
                    $this->servers[$sid]['status'] = 'offline';
                    $this->respond(0);
                }
                break;
            case 'serverstart':
                $sid = (int) $params['sid'];
                if (isset($this->servers[$sid])) {
                    $this->servers[$sid]['status'] = 'online';
                }
                $this->respond(isset($this->servers[$sid]) ? 0 : 1024);
                break;
            case 'serverdelete':
                $sid = (int) $params['sid'];
                if (isset($this->servers[$sid]) && $this->servers[$sid]['status'] === 'online') {
                    $this->respond(1025, 'server is running');

                    return;
                }
                unset($this->servers[$sid]);
                $this->respond(0);
                break;
            case 'serveredit':
                if ($this->selected === null) {
                    $this->respond(1024, 'invalid serverID');

                    return;
                }
                if (isset($params['virtualserver_autostart'])) {
                    $this->servers[$this->selected]['autostart'] = (int) $params['virtualserver_autostart'];
                }
                if (isset($params['virtualserver_maxclients'])) {
                    $this->servers[$this->selected]['slots'] = (int) $params['virtualserver_maxclients'];
                }
                $this->respond(0);
                break;
            case 'servergrouplist':
                $this->output[] = 'sgid=6 name=Server\sAdmin type=1|sgid=8 name=Guest type=1';
                $this->respond(0);
                break;
            case 'privilegekeyadd':
                $this->output[] = 'token=NEWKEY' . ($params['tokenid1'] ?? '');
                $this->respond(0);
                break;
            default:
                $this->respond(256, 'command not found');
        }
    }

    private function respond(int $id, string $message = 'ok'): void
    {
        $this->output[] = sprintf('error id=%d msg=%s', $id, ServerQueryClient::escape($message));
    }

    public function readLine(): string
    {
        if ($this->output === []) {
            throw new \RuntimeException('No pending ServerQuery output.');
        }

        return array_shift($this->output);
    }

    public function close(): void
    {
        $this->connected = false;
    }
}
