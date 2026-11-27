<?php

declare(strict_types=1);

namespace Nebrix\Integration;

use Nebrix\Core\Clock;
use Nebrix\Core\LoggerFactory;
use Nebrix\Database\Connection;
use Nebrix\Settings\SettingsRepository;
use Throwable;

/**
 * Per-integration log (nx_integration_logs) shown in the admin panel.
 * Context is redacted; debug entries are only written when verbose logging
 * is enabled in the system settings.
 */
class IntegrationLogger
{
    public function __construct(
        private readonly Connection $db,
        private readonly Clock $clock,
        private readonly SettingsRepository $settings,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(int $integrationId, string $level, string $action, string $message, array $context = [], ?int $durationMs = null): void
    {
        if ($level === 'debug' && !$this->verbose()) {
            return;
        }

        try {
            $this->db->insert('nx_integration_logs', [
                'integration_id' => $integrationId,
                'level' => $level,
                'action' => mb_substr($action, 0, 100),
                'message' => mb_substr($message, 0, 2000),
                'context' => $context === [] ? null : json_encode(LoggerFactory::redact($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'duration_ms' => $durationMs,
                'created_at' => $this->clock->nowString(),
            ]);
        } catch (Throwable) {
            // Logging must never break provisioning; the application log still has the error.
        }
    }

    private function verbose(): bool
    {
        try {
            return $this->settings->bool('system.debug_logging');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function paginate(int $integrationId, ?string $level, int $page, int $perPage = 50): array
    {
        $where = '`integration_id` = ?';
        $params = [$integrationId];
        if ($level !== null && $level !== '') {
            $where .= ' AND `level` = ?';
            $params[] = $level;
        }

        return [
            'items' => $this->db->fetchAll('SELECT * FROM `nx_integration_logs` WHERE ' . $where . ' ORDER BY `id` DESC LIMIT ? OFFSET ?', [...$params, $perPage, max(0, ($page - 1) * $perPage)]),
            'total' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM `nx_integration_logs` WHERE ' . $where, $params),
        ];
    }

    public function prune(int $retentionDays): int
    {
        return $this->db->run(
            'DELETE FROM `nx_integration_logs` WHERE `created_at` < ?',
            [$this->clock->now()->modify(sprintf('-%d days', max(1, $retentionDays)))->format('Y-m-d H:i:s')],
        )->rowCount();
    }
}
