<?php

declare(strict_types=1);

namespace Nebrix\Session;

/**
 * Server-side session. Data is JSON-serialized (never PHP-unserialized) and
 * stored in nx_sessions under a SHA-256 hash of the session ID.
 */
final class Session
{
    private bool $dirty = false;

    private ?string $previousId = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private string $id,
        private array $data,
        private readonly bool $isNew,
        private ?int $userId,
        private readonly int $createdAt,
        private readonly int $lastActivity,
    ) {
    }

    public static function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function createdAt(): int
    {
        return $this->createdAt;
    }

    public function lastActivity(): int
    {
        return $this->lastActivity;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->dirty = true;
    }

    public function forget(string $key): void
    {
        if (array_key_exists($key, $this->data)) {
            unset($this->data[$key]);
            $this->dirty = true;
        }
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    /** Stores a value that is available during the next request only. */
    public function flash(string $key, mixed $value): void
    {
        /** @var array{new?: array<string, mixed>, old?: array<string, mixed>} $flash */
        $flash = $this->data['_flash'] ?? [];
        $flash['new'][$key] = $value;
        $this->put('_flash', $flash);
    }

    /** Reads a value flashed by the previous request (or earlier in this one). */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        /** @var array{new?: array<string, mixed>, old?: array<string, mixed>} $flash */
        $flash = $this->data['_flash'] ?? [];

        return $flash['new'][$key] ?? $flash['old'][$key] ?? $default;
    }

    /** Called once at the start of each request. */
    public function ageFlash(): void
    {
        /** @var array{new?: array<string, mixed>, old?: array<string, mixed>} $flash */
        $flash = $this->data['_flash'] ?? [];
        if (($flash['new'] ?? []) === [] && ($flash['old'] ?? []) === []) {
            return;
        }
        $this->put('_flash', ['old' => $flash['new'] ?? [], 'new' => []]);
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
        $this->dirty = true;
    }

    /**
     * Issues a new session ID while keeping the data (session fixation defense).
     * The previous ID is remembered so the store can delete it.
     */
    public function regenerate(): void
    {
        $this->previousId ??= $this->id;
        $this->id = self::generateId();
        $this->dirty = true;
    }

    /** Clears all data and issues a new ID (logout). */
    public function invalidate(): void
    {
        $this->data = [];
        $this->userId = null;
        $this->regenerate();
    }

    public function previousId(): ?string
    {
        return $this->previousId;
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function isEmpty(): bool
    {
        return $this->data === [] && $this->userId === null;
    }
}
