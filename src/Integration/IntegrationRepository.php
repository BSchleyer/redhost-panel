<?php

declare(strict_types=1);

namespace Nebrix\Integration;

use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Security\Encrypter;

class IntegrationRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly Encrypter $encrypter,
        private readonly Clock $clock,
    ) {
    }

    public function find(int $id): ?Integration
    {
        $row = $this->db->fetchOne('SELECT * FROM `nx_integrations` WHERE `id` = ?', [$id]);

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Rows for listings (without secrets).
     *
     * @return list<array<string, mixed>>
     */
    public function listing(?string $type = null): array
    {
        $sql = 'SELECT i.`id`, i.`type`, i.`name`, i.`is_enabled`, i.`health_status`, i.`health_message`, i.`last_checked_at`, i.`created_at`,
                    (SELECT COUNT(*) FROM `nx_products` p WHERE p.`integration_id` = i.`id`) AS product_count,
                    (SELECT COUNT(*) FROM `nx_services` s WHERE s.`integration_id` = i.`id` AND s.`status` IN (\'active\', \'suspended\', \'provisioning\')) AS service_count
                FROM `nx_integrations` i';
        $params = [];
        if ($type !== null) {
            $sql .= ' WHERE i.`type` = ?';
            $params[] = $type;
        }

        return $this->db->fetchAll($sql . ' ORDER BY i.`type`, i.`name`', $params);
    }

    /**
     * @return list<int>
     */
    public function enabledIds(): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $this->db->fetchAll('SELECT `id` FROM `nx_integrations` WHERE `is_enabled` = 1'));
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $secrets
     */
    public function create(string $type, string $name, bool $enabled, array $config, array $secrets): int
    {
        $now = $this->clock->nowString();
        $id = $this->db->insert('nx_integrations', [
            'type' => $type,
            'name' => $name,
            'is_enabled' => $enabled,
            'config' => json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'secrets' => null,
            'health_status' => 'unknown',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->db->update('nx_integrations', ['secrets' => $this->encryptSecrets($id, $secrets)], ['id' => $id]);

        return $id;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string|null> $secretChanges new values; null removes a secret; missing keys stay unchanged
     */
    public function update(int $id, string $name, bool $enabled, array $config, array $secretChanges): void
    {
        $current = $this->find($id);
        if ($current === null) {
            throw new \RuntimeException('Integration not found.');
        }

        $secrets = [];
        foreach (array_keys(IntegrationType::get($current->type)['secrets']) as $key) {
            $value = array_key_exists($key, $secretChanges) ? $secretChanges[$key] : $current->secret($key);
            if ($value !== null && $value !== '') {
                $secrets[$key] = $value;
            }
        }

        $this->db->update('nx_integrations', [
            'name' => $name,
            'is_enabled' => $enabled,
            'config' => json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'secrets' => $this->encryptSecrets($id, $secrets),
            'updated_at' => $this->clock->nowString(),
        ], ['id' => $id]);
    }

    public function recordHealth(int $id, HealthResult $result): void
    {
        $this->db->update('nx_integrations', [
            'health_status' => $result->status,
            'health_message' => mb_substr($result->message, 0, 500),
            'last_checked_at' => $this->clock->nowString(),
        ], ['id' => $id]);
    }

    public function nameExists(string $type, string $name, int $exceptId = 0): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_integrations` WHERE `type` = ? AND `name` = ? AND `id` <> ?', [$type, $name, $exceptId]) > 0;
    }

    public function activeServiceCount(int $id): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM `nx_services` WHERE `integration_id` = ? AND `status` IN ('pending', 'provisioning', 'active', 'suspended')", [$id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('nx_integrations', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Integration
    {
        $config = json_decode((string) ($row['config'] ?? '{}'), true);
        $secrets = [];
        if (is_string($row['secrets']) && $row['secrets'] !== '') {
            /** @var array<string, string> $secrets */
            $secrets = $this->encrypter->decryptArray($row['secrets'], 'integration:' . (int) $row['id']);
        }

        return new Integration(
            (int) $row['id'],
            (string) $row['type'],
            (string) $row['name'],
            (bool) $row['is_enabled'],
            is_array($config) ? $config : [],
            $secrets,
        );
    }

    /**
     * @param array<string, string> $secrets
     */
    private function encryptSecrets(int $id, #[\SensitiveParameter] array $secrets): ?string
    {
        return $secrets === [] ? null : $this->encrypter->encryptArray($secrets, 'integration:' . $id);
    }
}
