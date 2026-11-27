<?php

declare(strict_types=1);

namespace Nebrix\Legacy;

use Nebrix\Billing\Money;
use Nebrix\Catalog\BillingCycle;
use Nebrix\Core\Clock;
use Nebrix\Database\Connection;
use Nebrix\Service\Service;
use Nebrix\Service\ServiceRepository;
use Throwable;

/**
 * Imports services, support tickets and the payment/balance history from a
 * RED-Host database (run after the user import).
 *
 * - Read-only on the legacy database; idempotent through nx_legacy_map /
 *   nx_legacy_records (re-running imports nothing twice).
 * - Services are linked to Nebrix integrations through an explicit mapping
 *   of the legacy hosts; unmapped hosts, deleted services and Venocix
 *   servers are reported and skipped. Nothing is changed on the remote
 *   systems; imported services are marked "legacy" so the provisioners
 *   accept the recorded remote IDs as theirs.
 * - Plaintext passwords stored by the legacy panel are imported encrypted.
 * - Legacy prices are taken as gross amounts (tax rate 0 on the service).
 */
class LegacyDataImporter
{
    public const STEP_SERVICES = 'services';
    public const STEP_TICKETS = 'tickets';
    public const STEP_HISTORY = 'history';

    public function __construct(
        private readonly Connection $target,
        private readonly ServiceRepository $services,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @param array{proxmox?: array<int, int>, teamspeak?: array<int, int>, plesk?: int|null, pterodactyl?: int|null} $mapping legacy host ID => integration ID
     * @return array{imported: int, skipped: int, conflicts: list<string>, errors: list<string>}
     */
    public function importServices(Connection $source, array $mapping, string $currency, bool $dryRun = false): array
    {
        $report = $this->emptyReport();
        $nodes = [];
        foreach ($this->rows($source, 'SELECT id, name FROM vm_host_nodes') as $node) {
            $nodes[(int) $node['id']] = (string) $node['name'];
        }

        foreach ($this->rows($source, 'SELECT * FROM vm_servers ORDER BY id') as $row) {
            $state = strtoupper((string) $row['state']);
            $label = 'vm_servers #' . $row['id'];
            if (in_array($state, ['DELETED', 'PENDING'], true)) {
                $report['skipped']++;
                continue;
            }
            if (strtoupper((string) ($row['api_name'] ?? '')) === 'VENOCIX') {
                $report['conflicts'][] = $label . ': Venocix servers are not supported';
                continue;
            }
            $integrationId = $mapping['proxmox'][(int) $row['node_id']] ?? null;
            if ($integrationId === null || !isset($nodes[(int) $row['node_id']])) {
                $report['conflicts'][] = sprintf('%s: no integration mapped for Proxmox node #%d', $label, (int) $row['node_id']);
                continue;
            }
            $isVm = strtoupper((string) ($row['type'] ?? 'LXC')) === 'KVM';
            $vmid = (int) $row['id'];
            $this->importService($source, $report, 'vm_servers', $row, $dryRun, [
                'integration_id' => $integrationId,
                'category' => $isVm ? 'vps' : 'lxc',
                'name' => $this->text($row['custom_name'] ?: $row['hostname'], 150) ?: ($isVm ? 'VPS' : 'Container') . ' #' . $vmid,
                'status' => $state === 'ACTIVE' ? Service::STATUS_ACTIVE : Service::STATUS_SUSPENDED,
                'external_id' => sprintf('pve:%s:%d', $nodes[(int) $row['node_id']], $vmid),
                'config' => ['cores' => (int) $row['cores'], 'memory_mb' => (int) $row['memory'], 'disk_gb' => (int) $row['disc'], '_options' => ['hostname' => (string) $row['hostname']]],
                'remote' => ['node' => $nodes[(int) $row['node_id']], 'vmid' => $vmid, 'type' => $isVm ? 'qemu' : 'lxc', 'step' => 'done', 'legacy' => true],
                'credentials' => ($row['password'] ?? '') !== '' ? ['username' => 'root', 'password' => (string) $row['password']] : [],
            ], $currency);
        }

        foreach ($this->rows($source, 'SELECT * FROM teamspeaks ORDER BY id') as $row) {
            $state = strtoupper((string) $row['state']);
            if ($state === 'DELETED') {
                $report['skipped']++;
                continue;
            }
            $integrationId = $mapping['teamspeak'][(int) $row['node_id']] ?? null;
            if ($integrationId === null) {
                $report['conflicts'][] = sprintf('teamspeaks #%d: no integration mapped for TeamSpeak host #%d', (int) $row['id'], (int) $row['node_id']);
                continue;
            }
            $this->importService($source, $report, 'teamspeaks', $row, $dryRun, [
                'integration_id' => $integrationId,
                'category' => 'teamspeak',
                'name' => $this->text($row['custom_name'], 150) ?: 'TeamSpeak #' . (int) $row['id'],
                'status' => $state === 'ACTIVE' ? Service::STATUS_ACTIVE : Service::STATUS_SUSPENDED,
                'external_id' => 'ts3:' . (int) $row['sid'],
                'config' => ['slots' => (int) $row['slots']],
                'remote' => ['sid' => (int) $row['sid'], 'port' => (int) $row['teamspeak_port'], 'legacy' => true],
                'credentials' => [],
            ], $currency);
        }

        foreach ($this->rows($source, 'SELECT * FROM pterodactyl_servers ORDER BY id') as $row) {
            if ($row['state'] === 'deleted') {
                $report['skipped']++;
                continue;
            }
            if (($mapping['pterodactyl'] ?? null) === null) {
                $report['conflicts'][] = sprintf('pterodactyl_servers #%d: no Pterodactyl integration mapped', (int) $row['id']);
                continue;
            }
            $this->importService($source, $report, 'pterodactyl_servers', $row, $dryRun, [
                'integration_id' => $mapping['pterodactyl'],
                'category' => 'minecraft',
                'name' => $this->text($row['custom_name'], 150) ?: 'Game server #' . (int) $row['id'],
                'status' => $row['state'] === 'active' ? Service::STATUS_ACTIVE : Service::STATUS_SUSPENDED,
                'external_id' => (string) $row['service_id'],
                'config' => ['memory_mb' => (int) $row['memory'], 'disk_mb' => (int) $row['disk'], 'cpu_percent' => (int) $row['cpu']],
                'remote' => ['server_id' => (int) $row['service_id'], 'identifier' => (string) $row['identifier'], 'legacy' => true],
                'credentials' => [],
            ], $currency);
        }

        foreach ($this->rows($source, 'SELECT * FROM webspace ORDER BY id') as $row) {
            if ($row['state'] === 'deleted') {
                $report['skipped']++;
                continue;
            }
            if (($mapping['plesk'] ?? null) === null) {
                $report['conflicts'][] = sprintf('webspace #%d: no Plesk integration mapped', (int) $row['id']);
                continue;
            }
            $this->importService($source, $report, 'webspace', $row, $dryRun, [
                'integration_id' => $mapping['plesk'],
                'category' => 'webhosting',
                'name' => $this->text($row['custom_name'] ?: $row['domainName'], 150) ?: 'Webspace #' . (int) $row['id'],
                'status' => $row['state'] === 'active' ? Service::STATUS_ACTIVE : Service::STATUS_SUSPENDED,
                'external_id' => 'plesk:' . (int) $row['webspace_id'],
                'config' => ['plan' => (string) $row['plan_id'], '_options' => ['domain' => mb_strtolower((string) $row['domainName'])]],
                'remote' => ['webspace_id' => (int) $row['webspace_id'], 'domain' => mb_strtolower((string) $row['domainName']), 'legacy' => true],
                'credentials' => ['ftp_user' => (string) $row['ftp_name'], 'ftp_password' => (string) $row['ftp_password']],
            ], $currency);
        }

        return $report;
    }

    /**
     * @return array{imported: int, skipped: int, conflicts: list<string>, errors: list<string>}
     */
    public function importTickets(Connection $source, bool $dryRun = false): array
    {
        $report = $this->emptyReport();
        $users = $this->userMap();
        $messages = [];
        foreach ($this->rows($source, 'SELECT * FROM ticket_message ORDER BY id') as $message) {
            $messages[(int) $message['ticket_id']][] = $message;
        }

        foreach ($this->rows($source, 'SELECT * FROM tickets ORDER BY id') as $row) {
            if ($this->mapped('tickets', (int) $row['id']) !== null) {
                $report['skipped']++;
                continue;
            }
            $userId = $users[(int) $row['user_id']] ?? null;
            if ($userId === null) {
                $report['conflicts'][] = sprintf('tickets #%d: customer not imported', (int) $row['id']);
                continue;
            }
            if ($dryRun) {
                $report['imported']++;
                continue;
            }

            try {
                $this->target->transaction(function () use ($row, $userId, $users, $messages): void {
                    $closed = strtoupper((string) $row['state']) === 'CLOSED';
                    $ticketId = $this->target->insert('nx_tickets', [
                        'user_id' => $userId,
                        'subject' => mb_substr(sprintf('[%s] %s', ucfirst(mb_strtolower((string) $row['categorie'])), $this->plain((string) $row['title'])), 0, 200),
                        'status' => $closed ? 'closed' : (strtoupper((string) $row['last_msg']) === 'SUPPORT' ? 'answered' : 'customer_reply'),
                        'priority' => match (strtoupper((string) $row['priority'])) { 'HOCH' => 'high', 'NIEDRIG' => 'low', default => 'normal' },
                        'created_at' => $this->date($row['created_at']),
                        'updated_at' => $this->date($row['updated_at']),
                        'closed_at' => $closed ? $this->date($row['updated_at']) : null,
                    ]);
                    foreach ($messages[(int) $row['id']] ?? [] as $message) {
                        $writer = $users[(int) $message['writer_id']] ?? null;
                        $this->target->insert('nx_ticket_messages', [
                            'ticket_id' => $ticketId,
                            'user_id' => $writer,
                            'is_staff' => (int) $message['writer_id'] !== (int) $row['user_id'] ? 1 : 0,
                            'message' => mb_substr($this->plain((string) $message['message']), 0, 20000) ?: '(empty message)',
                            'created_at' => $this->date($message['created_at']),
                        ]);
                    }
                    $this->map('tickets', (int) $row['id'], 'ticket', $ticketId);
                });
                $report['imported']++;
            } catch (Throwable $exception) {
                $report['errors'][] = sprintf('tickets #%d: %s', (int) $row['id'], $exception->getMessage());
            }
        }

        return $report;
    }

    /**
     * Payments (top-ups) and balance changes of the legacy panel as read-only
     * history; they are not booked again.
     *
     * @return array{imported: int, skipped: int, conflicts: list<string>, errors: list<string>}
     */
    public function importHistory(Connection $source, string $currency, bool $dryRun = false): array
    {
        $report = $this->emptyReport();
        $users = $this->userMap();
        $sources = [
            'payment' => 'SELECT id, user_id, gateway, state, amount, `desc` AS description, tid, created_at FROM transactions ORDER BY id',
            'balance_change' => 'SELECT id, user_id, amount, `desc` AS description, created_at FROM user_transactions ORDER BY id',
        ];

        foreach ($sources as $kind => $sql) {
            foreach ($this->rows($source, $sql) as $row) {
                if ($kind === 'payment' && ($row['state'] ?? '') !== 'success') {
                    $report['skipped']++;
                    continue;
                }
                if ((int) $this->target->fetchValue('SELECT COUNT(*) FROM `nx_legacy_records` WHERE `kind` = ? AND `legacy_id` = ?', [$kind, (int) $row['id']]) > 0) {
                    $report['skipped']++;
                    continue;
                }
                if ($dryRun) {
                    $report['imported']++;
                    continue;
                }
                $this->target->insert('nx_legacy_records', [
                    'user_id' => $users[(int) $row['user_id']] ?? null,
                    'kind' => $kind,
                    'legacy_id' => (int) $row['id'],
                    'amount' => $this->money($row['amount'], $currency, true),
                    'currency' => $currency,
                    'description' => $this->text($row['description'] ?? null, 255),
                    'data' => json_encode(array_filter(['gateway' => $row['gateway'] ?? null, 'reference' => $row['tid'] ?? null]), JSON_THROW_ON_ERROR),
                    'occurred_at' => $this->date($row['created_at'] ?? null),
                    'imported_at' => $this->clock->nowString(),
                ]);
                $report['imported']++;
            }
        }

        return $report;
    }

    /**
     * @param array{imported: int, skipped: int, conflicts: list<string>, errors: list<string>} $report
     * @param array<string, mixed> $row
     * @param array{integration_id: int, category: string, name: string, status: string, external_id: string, config: array<string, mixed>, remote: array<string, mixed>, credentials: array<string, scalar|null>} $service
     */
    private function importService(Connection $source, array &$report, string $table, array $row, bool $dryRun, array $service, string $currency): void
    {
        $legacyId = (int) $row['id'];
        if ($this->mapped($table, $legacyId) !== null) {
            $report['skipped']++;

            return;
        }
        $userId = $this->userMap()[(int) $row['user_id']] ?? null;
        if ($userId === null) {
            $report['conflicts'][] = sprintf('%s #%d: customer not imported', $table, $legacyId);

            return;
        }
        $cycle = match ((int) ($row['days'] ?? 30)) {
            90 => BillingCycle::QUARTERLY,
            180 => BillingCycle::SEMIANNUALLY,
            365, 366 => BillingCycle::ANNUALLY,
            default => BillingCycle::MONTHLY,
        };
        if (!in_array((int) ($row['days'] ?? 30), [0, 28, 29, 30, 31, 90, 180, 365, 366], true)) {
            $report['conflicts'][] = sprintf('%s #%d: runtime of %d days imported as monthly', $table, $legacyId, (int) $row['days']);
        }
        if ($dryRun) {
            $report['imported']++;

            return;
        }

        try {
            $this->target->transaction(function () use ($table, $legacyId, $userId, $row, $service, $cycle, $currency): void {
                $id = $this->services->create([
                    'user_id' => $userId,
                    'integration_id' => $service['integration_id'],
                    'category' => $service['category'],
                    'name' => $service['name'],
                    'status' => $service['status'],
                    'billing_cycle' => $cycle,
                    'recurring_amount' => $this->money($row['price'] ?? '0', $currency),
                    'tax_rate_bp' => 0,
                    'currency' => $currency,
                    'next_due_at' => $this->date($row['expire_at'] ?? null),
                    'external_id' => $service['external_id'],
                    'config' => $service['config'],
                    'suspension_reason' => $service['status'] === Service::STATUS_SUSPENDED ? 'Imported from RED-Host (suspended)' : null,
                    'provisioned_at' => $this->date($row['created_at'] ?? null),
                ]);
                $this->services->mergeRemote($id, $service['remote']);
                if ($service['credentials'] !== []) {
                    $this->services->setCredentials($id, $service['credentials']);
                }
                $this->map($table, $legacyId, 'service', $id);
            });
            $report['imported']++;
        } catch (Throwable $exception) {
            $report['errors'][] = sprintf('%s #%d: %s', $table, $legacyId, $exception->getMessage());
        }
    }

    /**
     * @return array{imported: int, skipped: int, conflicts: list<string>, errors: list<string>}
     */
    private function emptyReport(): array
    {
        return ['imported' => 0, 'skipped' => 0, 'conflicts' => [], 'errors' => []];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Connection $source, string $sql): array
    {
        try {
            return $source->fetchAll($sql);
        } catch (\PDOException $exception) {
            // Optional legacy tables (e.g. no TeamSpeak module installed).
            if (str_contains($exception->getMessage(), 'no such table') || str_contains($exception->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $exception;
        }
    }

    /**
     * @return array<int, int> legacy user ID => Nebrix user ID
     */
    private function userMap(): array
    {
        $map = [];
        foreach ($this->target->fetchAll('SELECT `id`, `legacy_id` FROM `nx_users` WHERE `legacy_id` IS NOT NULL') as $row) {
            $map[(int) $row['legacy_id']] = (int) $row['id'];
        }

        return $map;
    }

    private function mapped(string $table, int $legacyId): ?int
    {
        $id = $this->target->fetchValue('SELECT `entity_id` FROM `nx_legacy_map` WHERE `legacy_table` = ? AND `legacy_id` = ?', [$table, $legacyId]);

        return $id === null ? null : (int) $id;
    }

    private function map(string $table, int $legacyId, string $entity, int $entityId): void
    {
        $this->target->insert('nx_legacy_map', ['legacy_table' => $table, 'legacy_id' => $legacyId, 'entity' => $entity, 'entity_id' => $entityId, 'imported_at' => $this->clock->nowString()]);
    }

    private function money(mixed $amount, string $currency, bool $signed = false): int
    {
        $value = (float) $amount;
        $minor = Money::parse(number_format(abs($value), 2, '.', ''), $currency);

        return $signed && $value < 0 ? -$minor : $minor;
    }

    private function date(mixed $value): ?string
    {
        $time = is_string($value) && $value !== '' ? strtotime($value . ' UTC') : false;

        return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
    }

    private function text(mixed $value, int $length): ?string
    {
        $text = trim($this->plain((string) $value));

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    /** Legacy messages may contain HTML: keep the text only. */
    private function plain(string $value): string
    {
        $value = (string) preg_replace('#<br\s*/?>|</p>#i', "\n", $value);

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
