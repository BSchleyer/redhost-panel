<?php

declare(strict_types=1);

namespace Nebrix\Tests;

use Nebrix\Auth\Role;
use Nebrix\Auth\User;
use Nebrix\Core\Application;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Database\Migrator;
use Nebrix\Repository\UserRepository;
use Nebrix\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that need the real database schema.
 *
 * The schema is rebuilt once per PHPUnit run; each test runs inside a
 * transaction that is rolled back afterwards.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $schemaReady = false;

    protected Application $app;

    protected Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        $database = getenv('TEST_DB_DATABASE') ?: 'nebrix_test';
        if (getenv('TEST_DB_HOST') === false) {
            self::markTestSkipped('Database tests need TEST_DB_HOST (see tests/bootstrap.php).');
        }
        if (!str_contains($database, 'test')) {
            self::fail('Refusing to run against a database whose name does not contain "test".');
        }

        $this->app = Application::create(dirname(__DIR__), [
            'database' => [
                'host' => getenv('TEST_DB_HOST'),
                'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
                'database' => $database,
                'username' => getenv('TEST_DB_USERNAME') ?: 'nebrix',
                'password' => getenv('TEST_DB_PASSWORD') ?: 'nebrix',
                'socket' => '',
            ],
            // Generated invoice documents go to a temporary directory, not storage/.
            'paths' => ['invoices' => sys_get_temp_dir() . '/nebrix-test-invoices'],
        ], false);
        // Fast password hashing in tests.
        $this->app->container()->set(PasswordHasher::class, static fn (): PasswordHasher => new PasswordHasher(['memory_cost' => 8192, 'time_cost' => 1]));
        // No requests to the EU VIES service from tests.
        $vies = new \Nebrix\Tests\Support\FakeVatIdValidator();
        $this->app->container()->set(\Nebrix\Billing\Tax\VatIdValidator::class, static fn (): \Nebrix\Billing\Tax\VatIdValidator => $vies);

        $this->db = $this->app->container()->get(Connection::class);

        if (!self::$schemaReady) {
            $this->rebuildSchema();
            self::$schemaReady = true;
        }

        $this->db->pdo()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->isConnected() && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        parent::tearDown();
    }

    private function rebuildSchema(): void
    {
        $this->db->run('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->fetchAll("SHOW TABLES LIKE 'nx\\_%'") as $row) {
            $table = (string) array_values($row)[0];
            $this->db->run('DROP TABLE ' . $this->db->quoteIdentifier($table));
        }
        $this->db->run('SET FOREIGN_KEY_CHECKS = 1');

        $this->app->container()->get(Migrator::class)->migrate();
    }

    protected function clock(): Clock
    {
        return $this->app->container()->get(Clock::class);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function createUser(string $role = Role::CUSTOMER, array $attributes = []): User
    {
        $roleId = (int) $this->db->fetchValue('SELECT `id` FROM `nx_roles` WHERE `slug` = ?', [$role]);
        $hasher = $this->app->container()->get(PasswordHasher::class);
        $users = $this->app->container()->get(UserRepository::class);

        $id = $users->create($attributes + [
            'role_id' => $roleId,
            'email' => sprintf('%s-%s@example.test', $role, bin2hex(random_bytes(4))),
            'password_hash' => $hasher->hash('Valid-Password-123'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $this->clock()->nowString(),
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
        ]);

        $user = $users->find($id);
        self::assertNotNull($user);

        return $user;
    }
}
