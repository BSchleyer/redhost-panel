<?php

declare(strict_types=1);

namespace Nebrix\Settings;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Security\Encrypter;
use Nebrix\Security\ValidationException;
use Nebrix\Security\Validator;

/**
 * Runtime business settings stored in nx_settings.
 *
 * The schema (config/settings.php) defines type, default, validation rules
 * and whether a value is secret. Secret values are encrypted at rest and are
 * never returned to forms – the admin UI only shows whether they are set.
 */
class SettingsRepository
{
    /** @var array<string, string|null>|null */
    private ?array $values = null;

    /**
     * @param array<string, array{group: string, type: string, default?: mixed, label: string, help?: string, rules?: string, options?: array<string, string>, secret?: bool}> $schema
     */
    public function __construct(
        private readonly Connection $db,
        private readonly Encrypter $encrypter,
        private readonly Clock $clock,
        private readonly array $schema,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $key): array
    {
        if (!isset($this->schema[$key])) {
            throw new \InvalidArgumentException(sprintf('Unknown setting "%s".', $key));
        }

        return $this->schema[$key];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(string $group): array
    {
        return array_filter($this->schema, static fn (array $definition): bool => $definition['group'] === $group);
    }

    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $raw = $this->load()[$key] ?? null;

        if ($raw === null) {
            return $definition['default'] ?? null;
        }

        if (($definition['secret'] ?? false) === true) {
            try {
                return $this->encrypter->decrypt($raw, 'setting:' . $key);
            } catch (\RuntimeException) {
                return null;
            }
        }

        return $this->cast($definition['type'], $raw);
    }

    public function string(string $key): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : '';
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function int(string $key): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return list<string>
     */
    public function list(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    public function isSet(string $key): bool
    {
        return ($this->load()[$key] ?? null) !== null;
    }

    /**
     * Validates and stores the settings of one group.
     *
     * Secret fields left empty keep their current value; a dedicated
     * "<key>__clear" checkbox removes them.
     *
     * @param array<string, mixed> $input
     * @return list<string> keys that changed
     *
     * @throws ValidationException
     */
    public function saveGroup(string $group, array $input, ?int $actorId): array
    {
        $definitions = $this->definitions($group);
        $rules = [];
        $labels = [];
        $formInput = [];

        foreach ($definitions as $key => $definition) {
            $field = self::fieldName($key);
            $labels[$field] = $definition['label'];
            $rules[$field] = $definition['rules'] ?? $this->defaultRules($definition);
            $value = $input[$field] ?? null;

            if (($definition['secret'] ?? false) === true && ($value === null || $value === '')) {
                continue;
            }
            if ($definition['type'] === 'list') {
                $value = is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
            }
            if (array_key_exists($field, $input) || $definition['type'] === 'bool' || $definition['type'] === 'list') {
                $formInput[$field] = $value;
            }
        }

        $validated = Validator::validate($formInput, array_intersect_key($rules, $formInput), $labels);

        $changed = [];
        $this->db->transaction(function () use ($definitions, $validated, $input, $actorId, &$changed): void {
            foreach ($definitions as $key => $definition) {
                $field = self::fieldName($key);
                $isSecret = ($definition['secret'] ?? false) === true;

                if ($isSecret && filter_var($input[$field . '__clear'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $this->set($key, null, $actorId);
                    $changed[] = $key;
                    continue;
                }

                if (!array_key_exists($field, $validated)) {
                    continue;
                }

                $newValue = $validated[$field];
                if ($isSecret || $this->get($key) !== $this->cast($definition['type'], $this->serialize($definition['type'], $newValue))) {
                    $this->set($key, $newValue, $actorId);
                    $changed[] = $key;
                }
            }
        });

        return $changed;
    }

    public function set(string $key, mixed $value, ?int $actorId = null): void
    {
        $definition = $this->definition($key);
        $stored = null;
        if ($value !== null) {
            $stored = ($definition['secret'] ?? false) === true
                ? $this->encrypter->encrypt((string) $value, 'setting:' . $key)
                : $this->serialize($definition['type'], $value);
        }

        $now = $this->clock->nowString();
        $this->db->run(
            'INSERT INTO `nx_settings` (`key`, `value`, `updated_by`, `updated_at`) VALUES (:k, :v, :u, :t)
             ON DUPLICATE KEY UPDATE `value` = :v2, `updated_by` = :u2, `updated_at` = :t2',
            ['k' => $key, 'v' => $stored, 'u' => $actorId, 't' => $now, 'v2' => $stored, 'u2' => $actorId, 't2' => $now],
        );

        if ($this->values !== null) {
            $this->values[$key] = $stored;
        }
    }

    public static function fieldName(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function flush(): void
    {
        $this->values = null;
    }

    /**
     * @return array<string, string|null>
     */
    private function load(): array
    {
        if ($this->values === null) {
            $this->values = [];
            foreach ($this->db->fetchAll('SELECT `key`, `value` FROM `nx_settings`') as $row) {
                $this->values[(string) $row['key']] = $row['value'] !== null ? (string) $row['value'] : null;
            }
        }

        return $this->values;
    }

    private function serialize(string $type, mixed $value): string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'list' => json_encode(array_values((array) $value), JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    private function cast(string $type, ?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'bool' => $raw === '1',
            'int' => (int) $raw,
            'list' => json_decode($raw, true) ?: [],
            default => $raw,
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function defaultRules(array $definition): string
    {
        $rules = match ($definition['type']) {
            'bool' => 'bool',
            'int' => 'nullable|int|min:0',
            'email' => 'nullable|email',
            'url' => 'nullable|url|max:500',
            'color' => 'required|color',
            'text' => 'nullable|string|max:20000',
            'list' => 'nullable|array',
            default => 'nullable|string|max:500',
        };

        if (isset($definition['options']) && $definition['type'] === 'select') {
            $rules = 'required|in:' . implode(',', array_keys($definition['options']));
        }

        return $rules;
    }
}
