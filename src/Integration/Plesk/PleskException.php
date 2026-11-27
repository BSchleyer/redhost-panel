<?php

declare(strict_types=1);

namespace Nebrix\Integration\Plesk;

use Nebrix\Integration\IntegrationException;

/** Error reported by the Plesk XML API (errcode). */
final class PleskException extends IntegrationException
{
    public function __construct(string $message, public readonly int $errorCode)
    {
        parent::__construct($message);
    }
}
