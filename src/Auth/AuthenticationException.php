<?php

declare(strict_types=1);

namespace Nebrix\Auth;

use RuntimeException;

/** Authentication failure with a message that is safe to show to the user. */
final class AuthenticationException extends RuntimeException
{
    public const EMAIL_UNVERIFIED = 'email_unverified';

    public function __construct(string $message, public readonly string $reason = 'failed')
    {
        parent::__construct($message);
    }
}
