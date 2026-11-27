<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration;

use Nebrix\Auth\AuthService;
use Nebrix\Database\Connection;
use Nebrix\Legacy\LegacyImporter;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\PasswordHasher;
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
        self::assertCount(2, $report['conflicts']);

        $user = $this->db->fetchOne('SELECT u.*, r.slug FROM nx_users u JOIN nx_roles r ON r.id = u.role_id WHERE legacy_id = 1');
        self::assertNotNull($user);
        self::assertSame('gamer@example.test', $user['email']);
        self::assertSame('gamer01', $user['username']);
        self::assertSame('DE', $user['country']);
        self::assertSame('Hauptstr. 5', $user['address_line1']);
        self::assertSame(1250, (int) $user['credit_balance']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM nx_credit_ledger WHERE user_id = ?', [$user['id']]));

        self::assertSame('admin', $this->db->fetchValue('SELECT r.slug FROM nx_users u JOIN nx_roles r ON r.id = u.role_id WHERE legacy_id = 2'), 'Legacy admins never become super administrators.');
        self::assertSame('suspended', $this->db->fetchValue('SELECT status FROM nx_users WHERE legacy_id = 3'));

        // Second run imports nothing new and does not book the balance twice.
        $again = $importer->importUsers($source);
        self::assertSame(0, $again['imported']);
        self::assertSame(3, $again['skipped']);
        self::assertSame(1250, (int) $this->db->fetchValue('SELECT credit_balance FROM nx_users WHERE legacy_id = 1'));
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
