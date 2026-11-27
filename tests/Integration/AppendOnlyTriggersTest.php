<?php

declare(strict_types=1);

namespace Nebrix\Tests\Integration;

use Nebrix\Database\AppendOnlyTriggers;
use Nebrix\Database\Connection;
use Nebrix\Tests\DatabaseTestCase;

/**
 * The triggers are created through a second connection: DDL would commit
 * the test transaction of the main connection.
 */
final class AppendOnlyTriggersTest extends DatabaseTestCase
{
    private ?AppendOnlyTriggers $admin = null;

    protected function tearDown(): void
    {
        parent::tearDown(); // roll back first, then remove the triggers
        $this->admin?->remove();
    }

    public function testUpdatesAndDeletesAreRejected(): void
    {
        $second = new Connection([
            'host' => (string) getenv('TEST_DB_HOST'),
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'database' => (string) (getenv('TEST_DB_DATABASE') ?: 'nebrix_test'),
            'username' => (string) (getenv('TEST_DB_USERNAME') ?: 'nebrix'),
            'password' => (string) (getenv('TEST_DB_PASSWORD') ?: 'nebrix'),
        ]);
        $this->admin = new AppendOnlyTriggers($second);
        try {
            self::assertSame(6, $this->admin->install());
        } catch (\PDOException $exception) {
            self::markTestSkipped('The test database user may not create triggers: ' . $exception->getMessage());
        }
        self::assertSame(0, $this->admin->install(), 'Installing twice is a no-op.');
        self::assertSame([true], array_values(array_unique($this->admin->status())));

        $this->db->insert('nx_audit_log', ['actor_id' => null, 'action' => 'test.entry', 'created_at' => '2026-01-01 00:00:00']);
        foreach (["UPDATE nx_audit_log SET action = 'forged' WHERE action = 'test.entry'", "DELETE FROM nx_audit_log WHERE action = 'test.entry'", 'DELETE FROM nx_payment_events', 'UPDATE nx_credit_ledger SET amount = 0'] as $statement) {
            try {
                $this->db->run($statement);
                if (!str_contains($statement, 'nx_audit_log')) {
                    continue; // empty tables: the trigger only fires for existing rows
                }
                self::fail('Statement was not rejected: ' . $statement);
            } catch (\PDOException $exception) {
                self::assertStringContainsString('append-only', $exception->getMessage());
            }
        }
        self::assertSame('test.entry', $this->db->fetchValue("SELECT action FROM nx_audit_log WHERE action = 'test.entry'"));
    }
}
