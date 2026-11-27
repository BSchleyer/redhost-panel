<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration;

use Nebrix\Auth\AuthService;
use Nebrix\Database\Connection;
use Nebrix\Legacy\LegacyDataImporter;
use Nebrix\Legacy\LegacyImporter;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\PasswordHasher;
use Nebrix\Service\ServiceRepository;
use Nebrix\Tests\DatabaseTestCase;
use PDO;

final class LegacyImportTest extends DatabaseTestCase
{
    private function legacySource(): Connection
    {
        // Mirrors the relevant columns of the RED-Host `users` table.
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password TEXT, state TEXT, role TEXT, amount TEXT, firstname TEXT, lastname TEXT, street TEXT, number TEXT, postcode TEXT, city TEXT, country TEXT, legal_accepted INTEGER, created_at TEXT)');
        $insert = $pdo->prepare('INSERT INTO users VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $bcrypt = password_hash('old-secret-pass', PASSWORD_BCRYPT, ['cost' => 4]);
        $insert->execute([1, 'gamer01', 'Gamer@Example.test', $bcrypt, 'active', 'customer', '12.50', 'Max', 'Muster', 'Hauptstr.', '5', '45879', 'Gelsenkirchen', 'Deutschland', 1, '2021-04-18 10:00:00']);
        $insert->execute([2, 'boss', 'boss@example.test', $bcrypt, 'active', 'admin', '0.00', 'B', 'Oss', '', '', '', '', '', 1, '2021-04-18 10:00:00']);
        $insert->execute([3, 'banned', 'banned@example.test', $bcrypt, 'banned', 'customer', '0', '', '', '', '', '', '', 'AT', 0, '2021-05-01 10:00:00']);
        $insert->execute([4, 'broken', 'not-an-email', $bcrypt, 'active', 'customer', '0', '', '', '', '', '', '', '', 0, '2021-05-01 10:00:00']);
        $insert->execute([5, 'md5', 'md5@example.test', md5('x'), 'active', 'customer', '0', '', '', '', '', '', '', '', 0, '2021-05-01 10:00:00']);

        return Connection::fromPdo($pdo);
    }

    public function testImportsUsersIdempotentlyAndSafely(): void
    {
        $importer = $this->app->container()->get(LegacyImporter::class);
        $source = $this->legacySource();

        $dry = $importer->importUsers($source, true);
        self::assertSame(3, $dry['imported']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_users WHERE legacy_id IS NOT NULL'));

        $report = $importer->importUsers($source);
        self::assertSame(3, $report['imported']);
        self::assertCount(3, $report['conflicts']);

        $user = $this->db->fetchOne('SELECT u.*, r.slug FROM nx_users u JOIN nx_roles r ON r.id = u.role_id WHERE legacy_id = 1');
        self::assertNotNull($user);
        self::assertSame('gamer@example.test', $user['email']);
        self::assertSame('gamer01', $user['username']);
        self::assertSame('DE', $user['country']);
        self::assertSame('Hauptstr. 5', $user['address_line1']);
        self::assertSame(1250, (int) $user['credit_balance']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_credit_ledger WHERE user_id = ?', [$user['id']]));

        self::assertSame('admin', $this->db->fetchValue('SELECT r.slug FROM nx_users u JOIN nx_roles r ON r.id = u.role_id WHERE legacy_id = 2'), 'Legacy admins never become super administrators.');
        self::assertSame('suspended', $this->db->fetchValue('SELECT status FROM nx_users WHERE legacy_id = 2'), 'Legacy staff must be reviewed before they can sign in.');
        self::assertSame('suspended', $this->db->fetchValue('SELECT status FROM nx_users WHERE legacy_id = 3'));

        // Second run imports nothing new and does not book the balance twice.
        $again = $importer->importUsers($source);
        self::assertSame(0, $again['imported']);
        self::assertSame(3, $again['skipped']);
        self::assertSame(1250, (int) $this->db->fetchValue('SELECT credit_balance FROM nx_users WHERE legacy_id = 1'));
    }

    private function legacyData(Connection $source): void
    {
        $pdo = $source->pdo();
        $pdo->exec('CREATE TABLE vm_host_nodes (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO vm_host_nodes VALUES (1, 'pve1'), (2, 'pve-old')");
        $pdo->exec('CREATE TABLE vm_servers (id INTEGER PRIMARY KEY, user_id INT, hostname TEXT, password TEXT, node_id INT, cores INT, memory INT, disc INT, price TEXT, state TEXT, custom_name TEXT, expire_at TEXT, api_name TEXT, type TEXT, days INT, created_at TEXT)');
        $pdo->exec("INSERT INTO vm_servers VALUES
            (101, 1, 'web01', 'RootPw-Legacy1', 1, 2, 2048, 20, '9.99', 'ACTIVE', 'Webserver', '2026-12-15 00:00:00', 'NO_API', 'LXC', 30, '2025-01-01 10:00:00'),
            (102, 1, 'vm02', NULL, 1, 4, 4096, 50, '19.99', 'SUSPENDED', NULL, '2026-12-01 00:00:00', 'NO_API', 'KVM', 30, '2025-02-01 10:00:00'),
            (103, 1, 'gone', NULL, 1, 1, 512, 5, '1.00', 'DELETED', NULL, '2025-01-01 00:00:00', 'NO_API', 'LXC', 30, '2024-01-01 10:00:00'),
            (104, 1, 'veno', NULL, 1, 1, 512, 5, '1.00', 'ACTIVE', NULL, '2026-12-01 00:00:00', 'VENOCIX', 'KVM', 30, '2024-01-01 10:00:00'),
            (105, 1, 'unmapped', NULL, 2, 1, 512, 5, '1.00', 'ACTIVE', NULL, '2026-12-01 00:00:00', 'NO_API', 'LXC', 30, '2024-01-01 10:00:00'),
            (106, 99, 'stranger', NULL, 1, 1, 512, 5, '1.00', 'ACTIVE', NULL, '2026-12-01 00:00:00', 'NO_API', 'LXC', 30, '2024-01-01 10:00:00')");
        $pdo->exec('CREATE TABLE teamspeaks (id INTEGER PRIMARY KEY, user_id INT, slots INT, node_id INT, teamspeak_port TEXT, sid INT, expire_at TEXT, price TEXT, state TEXT, custom_name TEXT, days INT, created_at TEXT)');
        $pdo->exec("INSERT INTO teamspeaks VALUES (1, 1, 32, 1, '9988', 7, '2026-12-20 00:00:00', '4.00', 'ACTIVE', 'Clan', 365, '2025-03-01 00:00:00')");
        $pdo->exec('CREATE TABLE pterodactyl_servers (id INTEGER PRIMARY KEY, user_id INT, service_id TEXT, identifier TEXT, state TEXT, memory INT, cpu TEXT, disk TEXT, price TEXT, custom_name TEXT, expire_at TEXT, days INT, created_at TEXT)');
        $pdo->exec("INSERT INTO pterodactyl_servers VALUES (1, 1, '55', 'abcd1234', 'active', 4096, '200', '20480', '6.00', NULL, '2026-12-10 00:00:00', 30, '2025-04-01 00:00:00')");
        $pdo->exec('CREATE TABLE webspace (id INTEGER PRIMARY KEY, plan_id TEXT, user_id INT, ftp_name TEXT, ftp_password TEXT, domainName TEXT, webspace_id INT, state TEXT, expire_at TEXT, price TEXT, custom_name TEXT, days INT, created_at TEXT)');
        $pdo->exec("INSERT INTO webspace VALUES (1, 'Web S', 1, 'ftp_gamer', 'FtpPw-Legacy', 'Gamer-Site.de', 12, 'active', '2026-12-05 00:00:00', '3.00', NULL, 45, '2025-05-01 00:00:00')");
        $pdo->exec('CREATE TABLE tickets (id INTEGER PRIMARY KEY, user_id INT, categorie TEXT, priority TEXT, title TEXT, state TEXT, last_msg TEXT, created_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO tickets VALUES (1, 1, 'TECHNIK', 'HOCH', 'Server <b>down</b>', 'OPEN', 'SUPPORT', '2025-06-01 10:00:00', '2025-06-01 12:00:00'), (2, 99, 'ALLGEMEIN', 'MITTEL', 'Orphan', 'CLOSED', 'CUSTOMER', '2025-06-01 10:00:00', '2025-06-01 10:00:00')");
        $pdo->exec('CREATE TABLE ticket_message (id INTEGER PRIMARY KEY, ticket_id INT, writer_id INT, message TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO ticket_message VALUES (1, 1, 1, 'Hallo,<br>mein Server ist <script>alert(1)</script>weg', '2025-06-01 10:00:00'), (2, 1, 2, 'Wir schauen nach.', '2025-06-01 12:00:00')");
        $pdo->exec('CREATE TABLE transactions (id INTEGER PRIMARY KEY, user_id INT, gateway TEXT, state TEXT, amount TEXT, `desc` TEXT, tid TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO transactions VALUES (1, 1, 'paypal', 'success', '20.00', 'Aufladung', 'PAYID-1', '2025-01-01 09:00:00'), (2, 1, 'paypal', 'abort', '50.00', 'Abgebrochen', NULL, '2025-01-02 09:00:00')");
        $pdo->exec('CREATE TABLE user_transactions (id INTEGER PRIMARY KEY, user_id INT, amount TEXT, `desc` TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO user_transactions VALUES (1, 1, '-9.99', 'Verlängerung Webserver', '2025-02-01 09:00:00')");
    }

    public function testServicesTicketsAndHistoryAreImportedSafely(): void
    {
        $source = $this->legacySource();
        $this->legacyData($source);
        $this->app->container()->get(LegacyImporter::class)->importUsers($source);
        $repository = $this->app->container()->get(\Nebrix\Integration\IntegrationRepository::class);
        $proxmox = $repository->create('proxmox', 'PVE', true, ['base_url' => 'https://203.0.113.10:8006', 'token_id' => 'a@pve!b', 'verify_tls' => true], ['token_secret' => '0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0']);
        $teamspeak = $repository->create('teamspeak', 'TS', true, ['host' => '203.0.113.40', 'query_port' => 10011, 'query_user' => 'serveradmin', 'voice_host' => 'ts.example.com', 'port_min' => 9987, 'port_max' => 9999], ['query_password' => 'x']);
        $pterodactyl = $repository->create('pterodactyl', 'Ptero', true, ['base_url' => 'https://203.0.113.20', 'verify_tls' => true], ['application_api_key' => 'ptla_' . str_repeat('a', 30)]);
        $plesk = $repository->create('plesk', 'Plesk', true, ['base_url' => 'https://203.0.113.60:8443', 'ip_address' => '203.0.113.61', 'verify_tls' => true], ['secret_key' => 'k']);
        $importer = $this->app->container()->get(LegacyDataImporter::class);
        $mapping = ['proxmox' => [1 => $proxmox], 'teamspeak' => [1 => $teamspeak], 'pterodactyl' => $pterodactyl, 'plesk' => $plesk];

        $dry = $importer->importServices($source, $mapping, 'EUR', true);
        self::assertSame(5, $dry['imported']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_services'));

        $report = $importer->importServices($source, $mapping, 'EUR');
        self::assertSame(5, $report['imported']);
        self::assertSame(1, $report['skipped'], 'Deleted services are skipped.');
        $conflicts = implode("\n", $report['conflicts']);
        self::assertStringContainsString('vm_servers #104: Venocix', $conflicts);
        self::assertStringContainsString('vm_servers #105: no integration mapped', $conflicts);
        self::assertStringContainsString('vm_servers #106: customer not imported', $conflicts);
        self::assertStringContainsString('webspace #1: runtime of 45 days', $conflicts);

        $userId = (int) $this->db->fetchValue('SELECT id FROM nx_users WHERE legacy_id = 1');
        $services = $this->app->container()->get(ServiceRepository::class);
        $lxc = $services->find((int) $this->db->fetchValue("SELECT entity_id FROM nx_legacy_map WHERE legacy_table = 'vm_servers' AND legacy_id = 101"));
        self::assertNotNull($lxc);
        self::assertSame([$userId, 'lxc', 'active', 'Webserver', 999, 'pve:pve1:101', '2026-12-15 00:00:00'], [$lxc->userId, $lxc->category, $lxc->status, $lxc->name, $lxc->recurringAmount, $lxc->externalId, $lxc->nextDueAt]);
        self::assertSame(['node' => 'pve1', 'vmid' => 101, 'type' => 'lxc', 'step' => 'done', 'legacy' => true], $lxc->remote);
        self::assertSame('RootPw-Legacy1', $services->credentials($lxc)['password']);
        self::assertStringNotContainsString('RootPw-Legacy1', (string) $this->db->fetchValue('SELECT credentials FROM nx_services WHERE id = ?', [$lxc->id]), 'Legacy plaintext passwords are stored encrypted.');
        self::assertSame('suspended', $this->db->fetchValue("SELECT s.status FROM nx_services s JOIN nx_legacy_map m ON m.entity_id = s.id WHERE m.legacy_table = 'vm_servers' AND m.legacy_id = 102"));
        self::assertSame('annually', $this->db->fetchValue("SELECT billing_cycle FROM nx_services WHERE category = 'teamspeak'"));
        self::assertSame(['sid' => 7, 'port' => 9988, 'legacy' => true], json_decode((string) $this->db->fetchValue("SELECT remote FROM nx_services WHERE category = 'teamspeak'"), true));
        self::assertSame('gamer-site.de', json_decode((string) $this->db->fetchValue("SELECT remote FROM nx_services WHERE category = 'webhosting'"), true)['domain']);

        // Re-running imports nothing twice.
        $again = $importer->importServices($source, $mapping, 'EUR');
        self::assertSame(0, $again['imported']);
        self::assertSame(5, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_services'));

        // Tickets: HTML removed, staff replies flagged, orphans reported.
        $tickets = $importer->importTickets($source);
        self::assertSame(1, $tickets['imported']);
        self::assertStringContainsString('tickets #2: customer not imported', implode("\n", $tickets['conflicts']));
        $ticket = (array) $this->db->fetchOne('SELECT * FROM nx_tickets WHERE user_id = ?', [$userId]);
        self::assertSame(['[Technik] Server down', 'answered', 'high'], [$ticket['subject'], $ticket['status'], $ticket['priority']]);
        $messages = $this->db->fetchAll('SELECT * FROM nx_ticket_messages WHERE ticket_id = ? ORDER BY id', [$ticket['id']]);
        self::assertSame("Hallo,\nmein Server ist alert(1)weg", $messages[0]['message']);
        self::assertSame([0, 1], [(int) $messages[0]['is_staff'], (int) $messages[1]['is_staff']]);
        self::assertSame(0, $importer->importTickets($source)['imported']);

        // History: successful payments and balance changes only, never booked again.
        $history = $importer->importHistory($source, 'EUR');
        self::assertSame(2, $history['imported']);
        self::assertSame([2000, -999], array_map(static fn (array $r): int => (int) $r['amount'], $this->db->fetchAll('SELECT amount FROM nx_legacy_records WHERE user_id = ? ORDER BY kind DESC', [$userId])));
        self::assertSame(0, $importer->importHistory($source, 'EUR')['imported']);
        self::assertSame(1250, (int) $this->db->fetchValue('SELECT credit_balance FROM nx_users WHERE id = ?', [$userId]), 'History does not change the balance.');
    }

    public function testImportedLegacyPasswordWorksAndIsUpgraded(): void
    {
        $this->app->container()->get(LegacyImporter::class)->importUsers($this->legacySource());
        $user = $this->app->container()->get(UserRepository::class)->findByLogin('gamer01');
        self::assertNotNull($user);
        self::assertStringStartsWith('$2y$', $user->passwordHash);

        $request = \Nebrix\Http\Request::create('POST', '/login');
        $store = $this->app->container()->get(\Nebrix\Session\SessionStore::class);
        $request->setSession($store->create());
        self::assertSame('authenticated', $this->app->container()->get(AuthService::class)->attempt($request, 'gamer01', 'old-secret-pass'));

        $hash = (string) $this->db->fetchValue('SELECT password_hash FROM nx_users WHERE legacy_id = 1');
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($this->app->container()->get(PasswordHasher::class)->verify('old-secret-pass', $hash));
    }
}
