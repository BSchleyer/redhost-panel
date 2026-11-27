<?php

declare(strict_types=1);

namespace Nebrix\Integration;

/**
 * Supported integration types and the schema of their configuration.
 * "config" values are stored as JSON; "secrets" are encrypted and never
 * displayed again after saving.
 */
final class IntegrationType
{
    public const PROXMOX = 'proxmox';
    public const PTERODACTYL = 'pterodactyl';
    public const NEXTCLOUD = 'nextcloud';
    public const TEAMSPEAK = 'teamspeak';

    /**
     * @return array<string, array{label: string, description: string, config: array<string, array{label: string, rules: string, default?: mixed, help?: string, type?: string}>, secrets: array<string, array{label: string, rules: string, help?: string, required?: bool}>}>
     */
    public static function all(): array
    {
        return [
            self::PROXMOX => [
                'label' => 'Proxmox VE',
                'description' => 'KVM virtual machines and LXC containers on a Proxmox VE node or cluster.',
                'config' => [
                    'base_url' => ['label' => 'API URL', 'rules' => 'required|url|max:255', 'help' => 'e.g. https://pve.example.com:8006 – one entry per cluster.'],
                    'token_id' => ['label' => 'API token ID', 'rules' => 'required|string|max:200|regex:/^[A-Za-z0-9._-]+@[A-Za-z0-9._-]+![A-Za-z0-9._-]+$/', 'help' => 'Format user@realm!tokenname. Grant the token only the privileges it needs (see INTEGRATIONS.md).'],
                    'verify_tls' => ['label' => 'Verify TLS certificate', 'rules' => 'bool', 'default' => true, 'type' => 'bool', 'help' => 'Only disable for lab systems with self-signed certificates.'],
                ],
                'secrets' => [
                    'token_secret' => ['label' => 'API token secret', 'rules' => 'regex:/^[0-9a-fA-F-]{36}$/', 'required' => true],
                ],
            ],
            self::PTERODACTYL => [
                'label' => 'Pterodactyl',
                'description' => 'Game servers through the Pterodactyl Application API.',
                'config' => [
                    'base_url' => ['label' => 'Panel URL', 'rules' => 'required|url|max:255', 'help' => 'e.g. https://games.example.com'],
                    'verify_tls' => ['label' => 'Verify TLS certificate', 'rules' => 'bool', 'default' => true, 'type' => 'bool'],
                ],
                'secrets' => [
                    'application_api_key' => ['label' => 'Application API key (ptla_…)', 'rules' => 'regex:/^ptla_[A-Za-z0-9]{20,}$/', 'required' => true],
                    'client_api_key' => ['label' => 'Client API key of a root admin (ptlc_…, optional)', 'rules' => 'regex:/^ptlc_[A-Za-z0-9]{20,}$/', 'help' => 'Enables live status and power actions in the customer panel.'],
                ],
            ],
            self::NEXTCLOUD => [
                'label' => 'Nextcloud',
                'description' => 'Storage accounts through the Nextcloud OCS provisioning API.',
                'config' => [
                    'base_url' => ['label' => 'Nextcloud URL', 'rules' => 'required|url|max:255', 'help' => 'e.g. https://cloud.example.com'],
                    'admin_user' => ['label' => 'Admin user name', 'rules' => 'required|string|max:64|regex:/^[A-Za-z0-9._@-]+$/'],
                    'user_prefix' => ['label' => 'User ID prefix', 'rules' => 'required|string|max:16|regex:/^[a-z][a-z0-9_-]*$/', 'default' => 'nx', 'help' => 'Accounts are created as <prefix><service-id>.'],
                    'verify_tls' => ['label' => 'Verify TLS certificate', 'rules' => 'bool', 'default' => true, 'type' => 'bool'],
                ],
                'secrets' => [
                    'admin_password' => ['label' => 'Admin app password', 'rules' => 'string|max:255', 'required' => true, 'help' => 'Create an app password under Settings → Security in Nextcloud.'],
                ],
            ],
            self::TEAMSPEAK => [
                'label' => 'TeamSpeak 3',
                'description' => 'Virtual TeamSpeak 3 servers through the ServerQuery interface.',
                'config' => [
                    'host' => ['label' => 'ServerQuery host', 'rules' => 'required|hostname|max:253'],
                    'query_port' => ['label' => 'ServerQuery port', 'rules' => 'required|int|min:1|max:65535', 'default' => 10011],
                    'query_user' => ['label' => 'ServerQuery login', 'rules' => 'required|string|max:64|regex:/^[A-Za-z0-9._-]+$/', 'default' => 'serveradmin'],
                    'voice_host' => ['label' => 'Public host name for customers', 'rules' => 'required|hostname|max:253'],
                    'port_min' => ['label' => 'First voice port', 'rules' => 'required|int|min:1024|max:65535', 'default' => 9987],
                    'port_max' => ['label' => 'Last voice port', 'rules' => 'required|int|min:1024|max:65535', 'default' => 10986],
                ],
                'secrets' => [
                    'query_password' => ['label' => 'ServerQuery password', 'rules' => 'string|max:255', 'required' => true, 'help' => 'Raw ServerQuery is not encrypted: only connect over a private network or VPN.'],
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, config: array<string, array<string, mixed>>, secrets: array<string, array<string, mixed>>}
     */
    public static function get(string $type): array
    {
        $all = self::all();
        if (!isset($all[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown integration type "%s".', $type));
        }

        return $all[$type];
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(static fn (array $type): string => $type['label'], self::all());
    }
}
