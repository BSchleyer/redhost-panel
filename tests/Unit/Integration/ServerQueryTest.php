<?php

declare(strict_types=1);

namespace Nebrix\Tests\Unit\Integration;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Integration\IntegrationException;
use Nebrix\Integration\IntegrationLogger;
use Nebrix\Integration\TeamSpeak\ServerQueryClient;
use Nebrix\Integration\TeamSpeak\ServerQueryException;
use Nebrix\Integration\TeamSpeak\SocketTransport;
use Nebrix\Security\Encrypter;
use Nebrix\Settings\SettingsRepository;
use Nebrix\Tests\Support\FakeServerQuery;
use PHPUnit\Framework\TestCase;

final class ServerQueryTest extends TestCase
{
    private function logger(): IntegrationLogger
    {
        // Logging goes to an in-memory SQLite database that has no tables; the logger swallows the error by design.
        $db = Connection::fromPdo(new \PDO('sqlite::memory:'));

        return new IntegrationLogger($db, new Clock(), new SettingsRepository($db, Encrypter::fromAppKey(Encrypter::generateKey()), new Clock(), []));
    }

    private function client(FakeServerQuery $fake, string $password = 'secret'): ServerQueryClient
    {
        return new ServerQueryClient($fake, 'serveradmin', $password, $this->logger(), 1);
    }

    public function testEscapingRoundTrip(): void
    {
        $value = "My Server | with/slash\\ and\nnewline\ttab";
        $escaped = ServerQueryClient::escape($value);

        self::assertStringNotContainsString(' ', $escaped);
        self::assertStringNotContainsString('|', $escaped);
        self::assertStringNotContainsString("\n", $escaped);
        self::assertSame($value, ServerQueryClient::unescape($escaped));
    }

    public function testUserInputCannotInjectCommandsOrParameters(): void
    {
        $command = ServerQueryClient::buildCommand('servercreate', ['virtualserver_name' => "x\nserverdelete sid=1", 'virtualserver_port' => 9987]);

        self::assertStringNotContainsString("\n", $command);
        self::assertSame('servercreate virtualserver_name=x\\nserverdelete\\ssid=1 virtualserver_port=9987', $command);

        foreach ([['serverdelete sid', []], ['servercreate', ['bad key' => 1]], ['servercreate', ['k' => 1], ['-x y']]] as $case) {
            try {
                ServerQueryClient::buildCommand($case[0], $case[1], $case[2] ?? []);
                self::fail('Invalid command was accepted.');
            } catch (IntegrationException) {
                self::assertTrue(true);
            }
        }
    }

    public function testParsesMultiRecordResponses(): void
    {
        $record = ServerQueryClient::parseRecord('virtualserver_id=1 virtualserver_name=My\sServer\p1 virtualserver_port=9987');

        self::assertSame(['virtualserver_id' => '1', 'virtualserver_name' => 'My Server|1', 'virtualserver_port' => '9987'], $record);
    }

    public function testLoginCreateAndErrorHandling(): void
    {
        $fake = new FakeServerQuery();
        $client = $this->client($fake);

        $created = $client->createServer('Clan | Server', 32, 9987);
        self::assertSame(['sid' => 1, 'port' => 9987, 'token' => 'TOKEN1/abc'], $created);
        self::assertStringStartsWith('login client_login_name=serveradmin', $fake->commands[0]);

        try {
            $client->createServer('Duplicate', 10, 9987);
            self::fail('Expected an error for a used port.');
        } catch (ServerQueryException $exception) {
            self::assertSame(1028, $exception->queryErrorId());
        }

        self::assertSame('NEWKEY6', $client->createPrivilegeKey(1, 'test'));
    }

    public function testWrongPasswordFailsClearly(): void
    {
        $this->expectException(ServerQueryException::class);
        $this->expectExceptionMessage('ServerQuery error 520');
        $this->client(new FakeServerQuery('secret'), 'wrong')->listServers();
    }

    public function testOnlyVirtualServerPropertiesCanBeEdited(): void
    {
        $this->expectException(IntegrationException::class);
        $this->client(new FakeServerQuery())->editServer(1, ['client_nickname' => 'x']);
    }

    public function testSocketTransportAgainstRealTcpServer(): void
    {
        $portFile = tempnam(sys_get_temp_dir(), 'ts3port');
        $process = proc_open([PHP_BINARY, __DIR__ . '/../../Support/ts3_socket_server.php', $portFile], [], $pipes);
        self::assertIsResource($process);

        try {
            $port = 0;
            for ($i = 0; $i < 50 && $port === 0; $i++) {
                usleep(50_000);
                $port = (int) file_get_contents($portFile);
            }
            self::assertGreaterThan(0, $port, 'Fake server did not start.');

            $client = new ServerQueryClient(new SocketTransport('127.0.0.1', $port, 5), 'serveradmin', 's3cr/et x', $this->logger(), 1);
            $servers = $client->listServers();
            $client->disconnect();

            self::assertCount(2, $servers);
            self::assertSame('9988', $servers[1]['virtualserver_port']);
            self::assertSame('offline', $servers[1]['virtualserver_status']);
        } finally {
            proc_close($process);
            @unlink($portFile);
        }
    }
}
