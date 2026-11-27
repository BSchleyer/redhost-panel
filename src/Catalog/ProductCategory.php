<?php

declare(strict_types=1);

namespace Nebrix\Catalog;

/**
 * Product categories, their provisioning integration type and the schema of
 * category-specific settings.
 *
 * - "config" fields are set by administrators on the product and copied to
 *   the service when it is ordered.
 * - "options" are values the customer chooses when ordering (validated
 *   server-side with the given rules).
 *
 * Adding a category = adding an entry here (+ a provisioner if automated).
 */
final class ProductCategory
{
    public const VPS = 'vps';
    public const LXC = 'lxc';
    public const DEDICATED = 'dedicated';
    public const WEBHOSTING = 'webhosting';
    public const DOMAIN = 'domain';
    public const MINECRAFT = 'minecraft';
    public const TEAMSPEAK = 'teamspeak';
    public const NEXTCLOUD = 'nextcloud';

    private const HOSTNAME_RULE = 'required|string|max:63|regex:/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    /**
     * @return array<string, array{
     *     label: string,
     *     integration: string|null,
     *     config: array<string, array{label: string, rules: string, default?: mixed, help?: string, type?: string}>,
     *     options: array<string, array{label: string, rules: string, help?: string}>
     * }>
     */
    public static function all(): array
    {
        return [
            self::VPS => [
                'label' => 'VPS / KVM server',
                'integration' => 'proxmox',
                'config' => [
                    'node' => ['label' => 'Proxmox node (empty = node with most free memory)', 'rules' => 'nullable|string|max:64|regex:/^[A-Za-z0-9._-]*$/'],
                    'template_vmid' => ['label' => 'Template VM ID to clone', 'rules' => 'required|int|min:100|max:999999999', 'help' => 'A prepared cloud-init template on the selected node.'],
                    'storage' => ['label' => 'Target storage', 'rules' => 'required|string|max:64|regex:/^[A-Za-z0-9._-]+$/', 'default' => 'local-lvm'],
                    'cores' => ['label' => 'CPU cores', 'rules' => 'required|int|min:1|max:128', 'default' => 2],
                    'memory_mb' => ['label' => 'Memory (MB)', 'rules' => 'required|int|min:256|max:1048576', 'default' => 2048],
                    'disk_gb' => ['label' => 'Disk size (GB)', 'rules' => 'required|int|min:1|max:65536', 'default' => 40],
                    'bridge' => ['label' => 'Network bridge', 'rules' => 'required|string|max:32|regex:/^[A-Za-z0-9._-]+$/', 'default' => 'vmbr0'],
                    'rate_mbps' => ['label' => 'Network rate limit (MB/s, 0 = unlimited)', 'rules' => 'required|int|min:0|max:100000', 'default' => 0],
                    'vmid_start' => ['label' => 'First VM ID for new servers', 'rules' => 'required|int|min:100|max:999999999', 'default' => 10000],
                ],
                'options' => [
                    'hostname' => ['label' => 'Hostname', 'rules' => self::HOSTNAME_RULE, 'help' => 'Lowercase letters, digits and dashes.'],
                ],
            ],
            self::LXC => [
                'label' => 'LXC container',
                'integration' => 'proxmox',
                'config' => [
                    'node' => ['label' => 'Proxmox node (empty = node with most free memory)', 'rules' => 'nullable|string|max:64|regex:/^[A-Za-z0-9._-]*$/'],
                    'ostemplate' => ['label' => 'OS template', 'rules' => 'required|string|max:255|regex:/^[A-Za-z0-9._-]+:vztmpl\/[A-Za-z0-9._+-]+$/', 'help' => 'For example local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst'],
                    'storage' => ['label' => 'Root disk storage', 'rules' => 'required|string|max:64|regex:/^[A-Za-z0-9._-]+$/', 'default' => 'local-lvm'],
                    'cores' => ['label' => 'CPU cores', 'rules' => 'required|int|min:1|max:128', 'default' => 1],
                    'memory_mb' => ['label' => 'Memory (MB)', 'rules' => 'required|int|min:64|max:1048576', 'default' => 1024],
                    'swap_mb' => ['label' => 'Swap (MB)', 'rules' => 'required|int|min:0|max:1048576', 'default' => 512],
                    'disk_gb' => ['label' => 'Disk size (GB)', 'rules' => 'required|int|min:1|max:65536', 'default' => 10],
                    'bridge' => ['label' => 'Network bridge', 'rules' => 'required|string|max:32|regex:/^[A-Za-z0-9._-]+$/', 'default' => 'vmbr0'],
                    'rate_mbps' => ['label' => 'Network rate limit (MB/s, 0 = unlimited)', 'rules' => 'required|int|min:0|max:100000', 'default' => 0],
                    'unprivileged' => ['label' => 'Unprivileged container (recommended)', 'rules' => 'bool', 'default' => true, 'type' => 'bool'],
                    'vmid_start' => ['label' => 'First container ID for new servers', 'rules' => 'required|int|min:100|max:999999999', 'default' => 20000],
                ],
                'options' => [
                    'hostname' => ['label' => 'Hostname', 'rules' => self::HOSTNAME_RULE, 'help' => 'Lowercase letters, digits and dashes.'],
                ],
            ],
            self::DEDICATED => [
                'label' => 'Dedicated server',
                'integration' => null,
                'config' => [
                    'hardware' => ['label' => 'Hardware description', 'rules' => 'nullable|string|max:500'],
                ],
                'options' => [
                    'hostname' => ['label' => 'Hostname', 'rules' => self::HOSTNAME_RULE],
                ],
            ],
            self::WEBHOSTING => [
                'label' => 'Webhosting',
                'integration' => null,
                'config' => [
                    'plan' => ['label' => 'Hosting plan name on the hosting platform', 'rules' => 'nullable|string|max:100'],
                ],
                'options' => [
                    'domain' => ['label' => 'Domain', 'rules' => 'required|string|max:253|regex:/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
                ],
            ],
            self::DOMAIN => [
                'label' => 'Domain',
                'integration' => null,
                'config' => [
                    'tld' => ['label' => 'Top-level domain', 'rules' => 'required|string|max:63|regex:/^\.?[a-z0-9-]+(\.[a-z0-9-]+)*$/'],
                ],
                'options' => [
                    'domain' => ['label' => 'Domain', 'rules' => 'required|string|max:253|regex:/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'],
                ],
            ],
            self::MINECRAFT => [
                'label' => 'Minecraft / game server',
                'integration' => 'pterodactyl',
                'config' => [
                    'nest_id' => ['label' => 'Nest ID', 'rules' => 'required|int|min:1'],
                    'egg_id' => ['label' => 'Egg ID', 'rules' => 'required|int|min:1'],
                    'location_id' => ['label' => 'Location ID', 'rules' => 'required|int|min:1'],
                    'memory_mb' => ['label' => 'Memory (MB)', 'rules' => 'required|int|min:128|max:1048576', 'default' => 4096],
                    'swap_mb' => ['label' => 'Swap (MB, -1 = unlimited)', 'rules' => 'required|int|min:-1|max:1048576', 'default' => 0],
                    'disk_mb' => ['label' => 'Disk (MB)', 'rules' => 'required|int|min:256|max:10485760', 'default' => 20480],
                    'cpu_percent' => ['label' => 'CPU limit (% of one core, 0 = unlimited)', 'rules' => 'required|int|min:0|max:12800', 'default' => 200],
                    'io' => ['label' => 'Block IO weight (10-1000)', 'rules' => 'required|int|min:10|max:1000', 'default' => 500],
                    'databases' => ['label' => 'Databases', 'rules' => 'required|int|min:0|max:100', 'default' => 1],
                    'allocations' => ['label' => 'Additional ports', 'rules' => 'required|int|min:0|max:100', 'default' => 1],
                    'backups' => ['label' => 'Backups', 'rules' => 'required|int|min:0|max:100', 'default' => 2],
                    'docker_image' => ['label' => 'Docker image (empty = egg default)', 'rules' => 'nullable|string|max:255|regex:/^[A-Za-z0-9._\/:@-]*$/'],
                    'environment' => ['label' => 'Environment overrides (JSON object)', 'rules' => 'nullable|json|max:5000', 'type' => 'json', 'help' => 'Values for egg variables, e.g. {"SERVER_JARFILE": "server.jar"}. Missing variables use the egg defaults.'],
                ],
                'options' => [
                    'server_name' => ['label' => 'Server name', 'rules' => 'required|string|max:60|regex:/^[\p{L}\p{N} ._-]+$/u'],
                ],
            ],
            self::TEAMSPEAK => [
                'label' => 'TeamSpeak 3 server',
                'integration' => 'teamspeak',
                'config' => [
                    'slots' => ['label' => 'Slots', 'rules' => 'required|int|min:1|max:1024', 'default' => 32],
                ],
                'options' => [
                    'server_name' => ['label' => 'Server name', 'rules' => 'required|string|max:60|regex:/^[\p{L}\p{N} ._-]+$/u'],
                ],
            ],
            self::NEXTCLOUD => [
                'label' => 'Nextcloud storage',
                'integration' => 'nextcloud',
                'config' => [
                    'quota_gb' => ['label' => 'Storage quota (GB)', 'rules' => 'required|int|min:1|max:1048576', 'default' => 100],
                    'group' => ['label' => 'Nextcloud group (optional)', 'rules' => 'nullable|string|max:64|regex:/^[A-Za-z0-9 ._@-]*$/'],
                ],
                'options' => [],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(static fn (array $category): string => $category['label'], self::all());
    }

    /**
     * @return array{label: string, integration: string|null, config: array<string, array<string, mixed>>, options: array<string, array<string, mixed>>}
     */
    public static function get(string $category): array
    {
        $all = self::all();
        if (!isset($all[$category])) {
            throw new \InvalidArgumentException(sprintf('Unknown product category "%s".', $category));
        }

        return $all[$category];
    }

    public static function exists(string $category): bool
    {
        return isset(self::all()[$category]);
    }
}
