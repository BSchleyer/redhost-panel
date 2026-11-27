<?php

declare(strict_types=1);

namespace Nebrix\Integration\Inwx;

use Nebrix\Integration\IntegrationException;

/** Error result of the INWX API (result code). */
final class InwxException extends IntegrationException
{
    public function __construct(string $message, public readonly int $resultCode)
    {
        parent::__construct($message);
    }
}
