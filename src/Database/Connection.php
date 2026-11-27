<?php

declare(strict_types=1);

namespace Nebrix\Database;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper.
 *
 * - One lazily opened connection per process.
 * - Native prepared statements only (ATTR_EMULATE_PREPARES = false) with
 *   type-aware binding; there is no API that accepts raw values in SQL.
 * - Identifiers passed to the helper methods are validated against a strict
 *   pattern, so table/column names can never carry user input into SQL.
 * - utf8mb4 + strict SQL mode + UTC session time zone.
 */
class Connection
{
    private ?PDO $pdo = null;

    private int $transactionDepth = 0;

    /**
     * @param array{driver?: string, host?: string, port?: int|string, database?: string, username?: string, password?: string, socket?: string, dsn?: string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public static function fromPdo(PDO $pdo): self
    {
        $connection = new self([]);
        $connection->pdo = $pdo;

        return $connection;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }

        return $this->pdo;
    }

    /**
     * For long-running processes: drops a connection that the server closed
     * (idle timeout) so that the next query reconnects.
     */
    public function ensureAlive(): void
    {
        if ($this->pdo === null || $this->transactionDepth > 0) {
            return;
        }
        try {
            $this->pdo->query('SELECT 1');
        } catch (\Throwable) {
            // "server has gone away" (possibly raised as a converted warning): reconnect lazily.
            $this->pdo = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    private function connect(): PDO
    {
        $dsn = $this->config['dsn'] ?? null;
        if ($dsn === null || $dsn === '') {
            $dsn = isset($this->config['socket']) && $this->config['socket'] !== ''
                ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $this->config['socket'], $this->config['database'] ?? '')
                : sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $this->config['host'] ?? '127.0.0.1',
                    (int) ($this->config['port'] ?? 3306),
                    $this->config['database'] ?? '',
                );
        }

        $pdo = new PDO($dsn, $this->config['username'] ?? null, $this->config['password'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 10,
        ]);

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00', "
                . "sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        return $pdo;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $statement->bindValue($name, ...$this->bindable($value));
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @return array{0: mixed, 1: int}
     */
    private function bindable(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_bool($value) => [$value ? 1 : 0, PDO::PARAM_INT],
            is_int($value) => [$value, PDO::PARAM_INT],
            $value instanceof \DateTimeInterface => [
                (new \DateTimeImmutable('@' . $value->getTimestamp()))->format('Y-m-d H:i:s'),
                PDO::PARAM_STR,
            ],
            $value instanceof \BackedEnum => [$value->value, is_int($value->value) ? PDO::PARAM_INT : PDO::PARAM_STR],
            is_array($value) => [json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PDO::PARAM_STR],
            is_float($value), is_string($value), $value instanceof \Stringable => [(string) $value, PDO::PARAM_STR],
            default => throw new InvalidArgumentException('Unsupported parameter type: ' . get_debug_type($value)),
        };
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> */
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        if ($data === []) {
            throw new InvalidArgumentException('Cannot insert an empty row.');
        }

        $columns = array_map($this->quoteIdentifier(...), array_keys($data));
        $placeholders = array_map(static fn (string $column): string => ':' . $column, array_keys($data));

        $this->run(
            sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->quoteIdentifier($table), implode(', ', $columns), implode(', ', $placeholders)),
            $data,
        );

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where Equality conditions joined with AND (must not be empty).
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === [] || $where === []) {
            throw new InvalidArgumentException('Update requires data and a non-empty WHERE clause.');
        }

        $params = [];
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = sprintf('%s = :set_%s', $this->quoteIdentifier($column), $column);
            $params['set_' . $column] = $value;
        }

        [$whereSql, $whereParams] = $this->buildWhere($where);

        return $this->run(
            sprintf('UPDATE %s SET %s WHERE %s', $this->quoteIdentifier($table), implode(', ', $set), $whereSql),
            $params + $whereParams,
        )->rowCount();
    }

    /**
     * @param array<string, mixed> $where Equality conditions joined with AND (must not be empty).
     */
    public function delete(string $table, array $where): int
    {
        if ($where === []) {
            throw new InvalidArgumentException('Delete requires a non-empty WHERE clause.');
        }

        [$whereSql, $params] = $this->buildWhere($where);

        return $this->run(sprintf('DELETE FROM %s WHERE %s', $this->quoteIdentifier($table), $whereSql), $params)->rowCount();
    }

    /**
     * @param array<string, mixed> $where
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $where): array
    {
        $parts = [];
        $params = [];
        foreach ($where as $column => $value) {
            if ($value === null) {
                $parts[] = sprintf('%s IS NULL', $this->quoteIdentifier($column));
                continue;
            }
            $parts[] = sprintf('%s = :where_%s', $this->quoteIdentifier($column), $column);
            $params['where_' . $column] = $value;
        }

        return [implode(' AND ', $parts), $params];
    }

    public function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid SQL identifier "%s".', $identifier));
        }

        return '`' . $identifier . '`';
    }

    /**
     * Executes the callback inside a transaction. Nested calls join the outer
     * transaction; the outermost call commits or rolls back.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        // Join a transaction that was opened outside this wrapper (e.g. by the test suite).
        $isOutermost = $this->transactionDepth === 0 && !$pdo->inTransaction();

        if ($isOutermost) {
            $pdo->beginTransaction();
        }
        $this->transactionDepth++;

        try {
            $result = $callback($this);
            $this->transactionDepth--;
            if ($isOutermost) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            $this->transactionDepth--;
            if ($isOutermost && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Runs the callback while holding a named database lock (GET_LOCK), so
     * that several web requests or workers cannot run it concurrently.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withLock(string $name, int $timeoutSeconds, callable $callback): mixed
    {
        if ((int) $this->fetchValue('SELECT GET_LOCK(?, ?)', [$name, $timeoutSeconds]) !== 1) {
            throw new \RuntimeException(sprintf('Could not acquire the lock "%s" within %d seconds.', $name, $timeoutSeconds));
        }

        try {
            return $callback();
        } finally {
            $this->fetchValue('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    public function driver(): string
    {
        return (string) $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
