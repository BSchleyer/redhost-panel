<?php

declare(strict_types=1);

namespace Nebrix\Integration\TeamSpeak;

use Nebrix\Integration\IntegrationException;

final class ServerQueryException extends IntegrationException
{
    /** ServerQuery error IDs used by the provisioner. */
    public const INVALID_SERVER_ID = 1024;
    public const SERVER_NOT_RUNNING = 1033;

    public function __construct(string $message, private readonly int $queryErrorId)
    {
        parent::__construct($message, $queryErrorId === self::INVALID_SERVER_ID ? 404 : null);
    }

    public function queryErrorId(): int
    {
        return $this->queryErrorId;
    }
}
