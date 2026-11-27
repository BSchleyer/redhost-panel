<?php

declare(strict_types=1);

namespace Nebrix\Database;

/**
 * Optional database triggers that reject UPDATE and DELETE on the
 * append-only tables (defence in depth: the application never modifies
 * them). Creating triggers needs the TRIGGER privilege (and, with binary
 * logging, log_bin_trust_function_creators or SUPER).
 */
class AppendOnlyTriggers
{
    public const TABLES = ['nx_payment_events', 'nx_audit_log', 'nx_credit_ledger'];

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return array<string, bool> trigger name => exists
     */
    public function status(): array
    {
        $existing = array_map(
            static fn (array $row): string => (string) $row['TRIGGER_NAME'],
            $this->db->fetchAll('SELECT `TRIGGER_NAME` FROM information_schema.TRIGGERS WHERE `TRIGGER_SCHEMA` = DATABASE()'),
        );
        $status = [];
        foreach ($this->definitions() as $name => $sql) {
            $status[$name] = in_array($name, $existing, true);
        }

        return $status;
    }

    /** @return int number of triggers created */
    public function install(): int
    {
        $created = 0;
        foreach ($this->status() as $name => $exists) {
            if (!$exists) {
                $this->db->run($this->definitions()[$name]);
                $created++;
            }
        }

        return $created;
    }

    /** @return int number of triggers removed */
    public function remove(): int
    {
        $removed = 0;
        foreach ($this->status() as $name => $exists) {
            if ($exists) {
                $this->db->run('DROP TRIGGER ' . $this->db->quoteIdentifier($name));
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return array<string, string> trigger name => CREATE statement
     */
    private function definitions(): array
    {
        $definitions = [];
        foreach (self::TABLES as $table) {
            foreach (['UPDATE' => 'update', 'DELETE' => 'delete'] as $event => $suffix) {
                $name = $table . '_no_' . $suffix;
                $definitions[$name] = sprintf(
                    "CREATE TRIGGER %s BEFORE %s ON %s FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '%s is append-only'",
                    $this->db->quoteIdentifier($name),
                    $event,
                    $this->db->quoteIdentifier($table),
                    $table,
                );
            }
        }

        return $definitions;
    }
}
