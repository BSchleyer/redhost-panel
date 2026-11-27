<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

/**
 * A requested service operation is not possible in the current state.
 * The message is safe to show to customers.
 */
final class ServiceStateException extends \RuntimeException
{
}
