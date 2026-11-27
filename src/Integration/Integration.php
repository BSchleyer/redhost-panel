<?php

declare(strict_types=1);

namespace Nebrix\Integration;

/**
 * An integration instance (e.g. one Proxmox cluster or one Pterodactyl
 * panel) with decrypted secrets. Never pass this object to templates.
 */
final class Integration
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $secrets
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly array $config,
        #[\SensitiveParameter] private readonly array $secrets,
    ) {
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function secret(string $key): string
    {
        return $this->secrets[$key] ?? '';
    }

    public function hasSecret(string $key): bool
    {
        return ($this->secrets[$key] ?? '') !== '';
    }

    /** Prevents secrets from appearing in var_dump()/print_r() output. */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'name' => $this->name, 'enabled' => $this->enabled, 'config' => $this->config, 'secrets' => '[redacted]'];
    }
}
